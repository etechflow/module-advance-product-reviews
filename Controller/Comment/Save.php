<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Controller\Comment;

use ETechFlow\AdvancedProductReviews\Model\Config;
use ETechFlow\AdvancedProductReviews\Model\ReviewCommentFactory;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewComment as ReviewCommentResource;
use ETechFlow\AdvancedProductReviews\Model\Source\Status;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * AJAX: submit a comment on a review.
 */
class Save implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param CustomerSession $customerSession
     * @param Config $config
     * @param ReviewCommentFactory $commentFactory
     * @param ReviewCommentResource $commentResource
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly Config $config,
        private readonly ReviewCommentFactory $commentFactory,
        private readonly ReviewCommentResource $commentResource
    ) {
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        try {
            if (!$this->config->isEnabled()) {
                throw new LocalizedException(__('Reviews are unavailable.'));
            }
            if (!$this->config->isFlag(Config::XML_PATH_ENABLE_COMMENTS)) {
                throw new LocalizedException(__('Comments are disabled.'));
            }
            $isLoggedIn = $this->customerSession->isLoggedIn();
            if (!$isLoggedIn && !$this->config->isFlag(Config::XML_PATH_GUEST_COMMENTS)) {
                throw new LocalizedException(__('Please sign in to leave a comment.'));
            }

            $reviewId = (int) $this->request->getParam('review_id');
            $text = trim((string) $this->request->getParam('comment'));
            if ($reviewId <= 0 || $text === '') {
                throw new LocalizedException(__('Please enter a comment.'));
            }

            $authorName = $isLoggedIn
                ? trim($this->customerSession->getCustomer()->getName())
                : trim((string) $this->request->getParam('nickname'));
            if ($authorName === '') {
                $authorName = (string) __('Guest');
            }

            $autoApprove = $this->config->isFlag(Config::XML_PATH_AUTO_APPROVE);

            $comment = $this->commentFactory->create();
            $comment->setReviewId($reviewId)
                ->setCustomerId($isLoggedIn ? (int) $this->customerSession->getCustomerId() : null)
                ->setAuthorName($authorName)
                ->setComment($text)
                ->setIsAdminReply(false)
                ->setStatus($autoApprove ? Status::APPROVED : Status::PENDING);
            $this->commentResource->save($comment);

            $message = $autoApprove
                ? __('Your comment has been posted.')
                : __('Thank you! Your comment is awaiting moderation.');

            // Return the saved comment so the storefront can render it inline
            // without a page reload. Only appended client-side when approved.
            return $result->setData([
                'success'  => true,
                'approved' => $autoApprove,
                'message'  => $message,
                'comment'  => [
                    'author_name'    => $authorName,
                    'comment'        => $text,
                    'is_admin_reply' => false,
                ],
            ]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => __('Could not save your comment.')]);
        }
    }
}
