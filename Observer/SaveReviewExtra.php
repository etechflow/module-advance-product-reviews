<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
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

/**
 * On review submission, persist the extra data (pros/cons, recommend,
 * verified-buyer flag) and any uploaded photos/videos.
 */
class SaveReviewExtra implements ObserverInterface
{
    private const MEDIA_FIELD = 'etf_media';
    private const MAX_FILES = 10;

    /**
     * @param Request $request
     * @param Config $config
     * @param CustomerSession $customerSession
     * @param ReviewExtraRepositoryInterface $extraRepository
     * @param ReviewMediaFactory $mediaFactory
     * @param ReviewMediaResource $mediaResource
     * @param MediaUploader $mediaUploader
     * @param VerifiedBuyer $verifiedBuyer
     * @param LoggerInterface $logger
     */
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

    /**
     * @param Observer $observer
     * @return void
     */
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

        try {
            // Apply auto-approve logic if enabled
            if ($this->config->isFlag(Config::XML_PATH_AUTO_APPROVE)) {
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

            // Verified buyer detection.
            $productId = (int) $review->getEntityPkValue();
            if ($this->customerSession->isLoggedIn()) {
                $customerId = (int) $this->customerSession->getCustomerId();
                $extra->setIsVerifiedBuyer($this->verifiedBuyer->hasPurchased($customerId, $productId));
            }

            $this->extraRepository->save($extra);

            $this->saveMedia($reviewId);
        } catch (\Exception $e) {
            // Never break the core review save flow because of our extras.
            $this->logger->error(
                '[ETechFlow_AdvancedProductReviews] Failed saving review extras: ' . $e->getMessage()
            );
        }
    }

    /**
     * Persist uploaded media files for the review.
     *
     * @param int $reviewId
     * @return void
     */
    private function saveMedia(int $reviewId): void
    {
        $files = $this->request->getFiles()->toArray();
        
        // DEBUG: Log file upload attempt
        $this->logger->info(
            sprintf('[ETechFlow] SaveMedia called for review %d. Files received: %s', 
                $reviewId, 
                !empty($files[self::MEDIA_FIELD]) ? 'YES' : 'NO'
            )
        );
        
        if (empty($files[self::MEDIA_FIELD]['name'])) {
            $this->logger->info('[ETechFlow] No media files to save - field empty');
            return;
        }
        
        $names = $files[self::MEDIA_FIELD]['name'];
        $names = is_array($names) ? $names : [$names];
        $sort = 0;

        $this->logger->info(
            sprintf('[ETechFlow] Processing %d file(s) for review %d', count($names), $reviewId)
        );

        foreach (array_keys($names) as $index) {
            if ($sort >= self::MAX_FILES) {
                break;
            }
            if (($files[self::MEDIA_FIELD]['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $this->logger->info(
                    sprintf('[ETechFlow] Skipping file %d - upload error code: %d', 
                        $index, 
                        $files[self::MEDIA_FIELD]['error'][$index] ?? UPLOAD_ERR_NO_FILE
                    )
                );
                continue;
            }
            try {
                $info = $this->mediaUploader->upload([self::MEDIA_FIELD, $index]);
                if ($info === null) {
                    $this->logger->warning('[ETechFlow] MediaUploader returned null for index ' . $index);
                    continue;
                }
                
                $this->logger->info(
                    sprintf('[ETechFlow] File uploaded successfully: %s (type: %s)', 
                        $info['file'], 
                        $info['type']
                    )
                );
                
                $media = $this->mediaFactory->create();
                $media->setReviewId($reviewId)
                    ->setMediaType($info['type'])
                    ->setData('file_path', $info['file'])
                    ->setData('mime_type', $info['mime'])
                    ->setData('sort_order', $sort);
                $this->mediaResource->save($media);
                
                $this->logger->info(
                    sprintf('[ETechFlow] Media saved to DB with ID: %d', $media->getId())
                );
                
                $sort++;
            } catch (\Exception $e) {
                $this->logger->warning(
                    '[ETechFlow_AdvancedProductReviews] Media upload skipped: ' . $e->getMessage()
                );
            }
        }
        
        $this->logger->info(
            sprintf('[ETechFlow] SaveMedia completed. Total saved: %d files', $sort)
        );
    }

    /**
     * Trim and cap a pros/cons text blob.
     *
     * @param string $text
     * @return string
     */
    private function cleanText(string $text): string
    {
        $text = trim($text);
        return mb_substr($text, 0, 2000);
    }
}
