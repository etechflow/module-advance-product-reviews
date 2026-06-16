<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model;

use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewComment as ReviewCommentResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Comment on a review (or admin/store-representative reply).
 *
 * @method int getReviewId()
 * @method $this setReviewId(int $reviewId)
 * @method int|null getParentId()
 * @method $this setParentId(?int $parentId)
 * @method int|null getCustomerId()
 * @method $this setCustomerId(?int $customerId)
 * @method string getAuthorName()
 * @method $this setAuthorName(string $name)
 * @method string getComment()
 * @method $this setComment(string $comment)
 * @method int getStatus()
 * @method $this setStatus(int $status)
 */
class ReviewComment extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'etechflow_review_comment';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ReviewCommentResource::class);
    }

    /**
     * @return bool
     */
    public function isAdminReply(): bool
    {
        return (bool) $this->getData('is_admin_reply');
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setIsAdminReply(bool $value): self
    {
        return $this->setData('is_admin_reply', $value ? 1 : 0);
    }

    /**
     * @return bool
     */
    public function isVisibleInAccountOnly(): bool
    {
        return (bool) $this->getData('visible_in_account_only');
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setVisibleInAccountOnly(bool $value): self
    {
        return $this->setData('visible_in_account_only', $value ? 1 : 0);
    }
}
