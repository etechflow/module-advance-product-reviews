<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Controller\Vote;

use ETechFlow\AdvancedProductReviews\Api\ReviewExtraRepositoryInterface;
use ETechFlow\AdvancedProductReviews\Model\ReviewVoteFactory;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewVote as ReviewVoteResource;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Header as HttpHeader;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/**
 * AJAX: record a "was this helpful?" vote.
 */
class Save implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param CustomerSession $customerSession
     * @param ReviewVoteFactory $voteFactory
     * @param ReviewVoteResource $voteResource
     * @param ReviewExtraRepositoryInterface $extraRepository
     * @param RemoteAddress $remoteAddress
     * @param HttpHeader $httpHeader
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly ReviewVoteFactory $voteFactory,
        private readonly ReviewVoteResource $voteResource,
        private readonly ReviewExtraRepositoryInterface $extraRepository,
        private readonly RemoteAddress $remoteAddress,
        private readonly HttpHeader $httpHeader
    ) {
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        try {
            $reviewId = (int) $this->request->getParam('review_id');
            $isHelpful = (bool) $this->request->getParam('helpful');
            if ($reviewId <= 0) {
                throw new LocalizedException(__('Invalid review.'));
            }

            $customerId = $this->customerSession->isLoggedIn()
                ? (int) $this->customerSession->getCustomerId()
                : 0;
            $visitorHash = $this->buildVisitorHash();

            // Check if user has already voted
            $existingVote = null;
            if ($customerId > 0) {
                $existingVote = $this->getExistingVoteByCustomer($reviewId, $customerId);
            } else {
                $existingVote = $this->getExistingVoteByGuest($reviewId, $visitorHash);
            }

            $extra = $this->extraRepository->getByReviewId($reviewId);

            // If user already voted with the same value, remove the vote (toggle off)
            if ($existingVote && $existingVote->getIsHelpful() === $isHelpful) {
                // Remove the vote
                if ($isHelpful) {
                    $extra->setHelpfulCount(max(0, $extra->getHelpfulCount() - 1));
                } else {
                    $extra->setNotHelpfulCount(max(0, $extra->getNotHelpfulCount() - 1));
                }
                $this->voteResource->delete($existingVote);
                $this->extraRepository->save($extra);

                return $result->setData([
                    'success' => true,
                    'removed' => true,
                    'helpful_count' => $extra->getHelpfulCount(),
                    'not_helpful_count' => $extra->getNotHelpfulCount(),
                ]);
            }

            // If user voted with opposite value, change the vote
            if ($existingVote && $existingVote->getIsHelpful() !== $isHelpful) {
                // Update existing vote
                $oldIsHelpful = $existingVote->getIsHelpful();
                $existingVote->setIsHelpful($isHelpful);
                $this->voteResource->save($existingVote);

                // Update counts: decrement old, increment new
                if ($oldIsHelpful) {
                    $extra->setHelpfulCount(max(0, $extra->getHelpfulCount() - 1));
                    $extra->setNotHelpfulCount($extra->getNotHelpfulCount() + 1);
                } else {
                    $extra->setNotHelpfulCount(max(0, $extra->getNotHelpfulCount() - 1));
                    $extra->setHelpfulCount($extra->getHelpfulCount() + 1);
                }
                $this->extraRepository->save($extra);

                return $result->setData([
                    'success' => true,
                    'changed' => true,
                    'helpful_count' => $extra->getHelpfulCount(),
                    'not_helpful_count' => $extra->getNotHelpfulCount(),
                ]);
            }

            // New vote - create it
            $vote = $this->voteFactory->create();
            $vote->setReviewId($reviewId)
                ->setCustomerId($customerId ?: null)
                ->setVisitorHash($visitorHash)
                ->setIsHelpful($isHelpful);
            $this->voteResource->save($vote);

            // Update aggregate counts on the extra row.
            if ($isHelpful) {
                $extra->setHelpfulCount($extra->getHelpfulCount() + 1);
            } else {
                $extra->setNotHelpfulCount($extra->getNotHelpfulCount() + 1);
            }
            $this->extraRepository->save($extra);

            return $result->setData([
                'success' => true,
                'helpful_count' => $extra->getHelpfulCount(),
                'not_helpful_count' => $extra->getNotHelpfulCount(),
            ]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => __('Could not record your vote.')]);
        }
    }

    /**
     * Build a stable, privacy-preserving hash to dedupe guest votes.
     *
     * @return string
     */
    private function buildVisitorHash(): string
    {
        $ip = (string) $this->remoteAddress->getRemoteAddress();
        $ua = (string) $this->httpHeader->getHttpUserAgent();
        return hash('sha256', $ip . '|' . $ua);
    }

    /**
     * Get existing vote by customer ID.
     *
     * @param int $reviewId
     * @param int $customerId
     * @return \ETechFlow\AdvancedProductReviews\Model\ReviewVote|null
     */
    private function getExistingVoteByCustomer(int $reviewId, int $customerId): ?\ETechFlow\AdvancedProductReviews\Model\ReviewVote
    {
        $connection = $this->voteResource->getConnection();
        $select = $connection->select()
            ->from($this->voteResource->getMainTable())
            ->where('review_id = ?', $reviewId)
            ->where('customer_id = ?', $customerId)
            ->limit(1);
        
        $data = $connection->fetchRow($select);
        if (!$data) {
            return null;
        }
        
        $vote = $this->voteFactory->create();
        $vote->setData($data);
        return $vote;
    }

    /**
     * Get existing vote by visitor hash (guest).
     *
     * @param int $reviewId
     * @param string $visitorHash
     * @return \ETechFlow\AdvancedProductReviews\Model\ReviewVote|null
     */
    private function getExistingVoteByGuest(int $reviewId, string $visitorHash): ?\ETechFlow\AdvancedProductReviews\Model\ReviewVote
    {
        $connection = $this->voteResource->getConnection();
        $select = $connection->select()
            ->from($this->voteResource->getMainTable())
            ->where('review_id = ?', $reviewId)
            ->where('visitor_hash = ?', $visitorHash)
            ->limit(1);
        
        $data = $connection->fetchRow($select);
        if (!$data) {
            return null;
        }
        
        $vote = $this->voteFactory->create();
        $vote->setData($data);
        return $vote;
    }
}
