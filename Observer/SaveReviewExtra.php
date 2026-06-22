<?php
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Observer;

use ETechFlow\AdvancedProductReviews\Api\ReviewExtraRepositoryInterface;
use ETechFlow\AdvancedProductReviews\Model\Config;
use ETechFlow\AdvancedProductReviews\Model\ReviewMediaFactory;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewMedia as ReviewMediaResource;
use ETechFlow\AdvancedProductReviews\Model\Service\MediaUploader;
use ETechFlow\AdvancedProductReviews\Model\Service\VerifiedBuyer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Review\Model\Review;
use Psr\Log\LoggerInterface;

class SaveReviewExtra implements ObserverInterface
{
    private const MEDIA_FIELD = 'etf_media';
    private const MAX_FILES = 10;

    /**
     * Guards against infinite recursion. This observer is bound to
     * review_save_after; the auto-approve save() below re-dispatches that
     * same event, which would re-enter execute() and recurse until the call
     * stack overflows (zend.max_allowed_stack_size). Each review is therefore
     * processed at most once per request.
     *
     * @var array<int,bool>
     */
    private static array $processed = [];

    public function __construct(
        private readonly Request $request,
        private readonly Config $config,
        private readonly CustomerSession $customerSession,
        private readonly ReviewExtraRepositoryInterface $extraRepository,
        private readonly ReviewMediaFactory $mediaFactory,
        private readonly ReviewMediaResource $mediaResource,
        private readonly MediaUploader $mediaUploader,
        private readonly VerifiedBuyer $verifiedBuyer,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        /** @var \Magento\Review\Model\Review $review */
        $review = $observer->getEvent()->getDataObject();
        if (!$review || !$review->getId()) {
            return;
        }

        $reviewId = (int) $review->getId();

        // Re-entry guard: the auto-approve save() re-dispatches review_save_after,
        // which calls this observer again. Without this, the second pass would
        // save() once more and recurse infinitely. Bail on any re-entry so the
        // extras/media are also processed exactly once.
        if (isset(self::$processed[$reviewId])) {
            return;
        }
        self::$processed[$reviewId] = true;

        try {
            if ($this->config->isFlag(Config::XML_PATH_AUTO_APPROVE)
                && (int) $review->getStatusId() !== Review::STATUS_APPROVED
            ) {
                $review->setStatusId(Review::STATUS_APPROVED)->save();
            }

            $extra = $this->extraRepository->getByReviewId($reviewId);

            if ($this->config->isFlag(Config::XML_PATH_ENABLE_PROS_CONS)) {
                $extra->setPros($this->cleanText((string) $this->request->getParam('etf_pros')));
                $extra->setCons($this->cleanText((string) $this->request->getParam('etf_cons')));
            }

            if ($this->config->isFlag(Config::XML_PATH_ENABLE_RECOMMEND)) {
                $extra->setIsRecommended((bool) $this->request->getParam('etf_recommend'));
            }

            $productId = (int) $review->getEntityPkValue();
            if ($this->customerSession->isLoggedIn()) {
                $customerId = (int) $this->customerSession->getCustomerId();
                $extra->setIsVerifiedBuyer($this->verifiedBuyer->hasPurchased($customerId, $productId));
            }

            $this->extraRepository->save($extra);

            $this->saveMedia($reviewId);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('[ETechFlow] Failed saving review extras for review %d: %s', $reviewId, $e->getMessage())
            );
        }
    }

    /**
     * Store any uploaded photos/videos for the review.
     *
     * @param int $reviewId
     * @return void
     */
    private function saveMedia(int $reviewId): void
    {
        $files = $this->request->getFiles()->toArray();
        $entries = $this->extractFileEntries($files[self::MEDIA_FIELD] ?? []);
        if (empty($entries)) {
            return;
        }

        $sort = 0;
        foreach ($entries as $index => $entry) {
            if ($sort >= self::MAX_FILES) {
                break;
            }
            if (empty($entry['name'])) {
                continue;
            }
            if ((int) ($entry['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            try {
                // Magento's Uploader references a multi-file input by the string
                // "field[index]" (an array fileId is treated as literal file data).
                $info = $this->mediaUploader->upload(self::MEDIA_FIELD . '[' . $index . ']');
                if ($info === null) {
                    continue;
                }

                $media = $this->mediaFactory->create();
                $media->setReviewId($reviewId)
                    ->setMediaType($info['type'])
                    ->setData('file_path', $info['file'])
                    ->setData('mime_type', $info['mime'] ?? '')
                    ->setData('sort_order', $sort);
                $this->mediaResource->save($media);

                $sort++;
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    '[ETechFlow] Media upload failed for index %s: %s',
                    $index,
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Normalize a $_FILES field to [index => ['name' => .., 'error' => ..]] for
     * both the raw $_FILES layout and the per-index layout that Magento's
     * Request::getFiles() (Laminas) produces for multi-file inputs
     * (name="etf_media[]").
     *
     * @param array $field
     * @return array<int|string,array<string,mixed>>
     */
    private function extractFileEntries(array $field): array
    {
        if (empty($field)) {
            return [];
        }
        // Laminas-normalized multi-file: [0 => ['name'=>.., 'error'=>..], ...]
        if (isset($field[0]) && is_array($field[0])) {
            return $field;
        }
        // Raw $_FILES multi-file: ['name'=>[..], 'error'=>[..], ...]
        if (isset($field['name']) && is_array($field['name'])) {
            $out = [];
            foreach (array_keys($field['name']) as $i) {
                $out[$i] = [
                    'name' => $field['name'][$i],
                    'error' => $field['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                ];
            }
            return $out;
        }
        // Raw $_FILES single file: ['name'=>'x', 'error'=>0, ...]
        if (isset($field['name'])) {
            return [0 => ['name' => $field['name'], 'error' => $field['error'] ?? UPLOAD_ERR_NO_FILE]];
        }
        return [];
    }

    private function cleanText(?string $text): string
    {
        if (!$text) {
            return '';
        }
        return trim(strip_tags($text));
    }
}
