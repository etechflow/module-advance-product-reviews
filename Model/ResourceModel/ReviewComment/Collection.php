<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewComment;

use ETechFlow\AdvancedProductReviews\Model\ReviewComment as ReviewCommentModel;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewComment as ReviewCommentResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Review comment collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'comment_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ReviewCommentModel::class, ReviewCommentResource::class);
    }

    /**
     * Approved comments for a review, oldest first.
     *
     * @param int $reviewId
     * @param int $status
     * @return $this
     */
    public function addReviewFilter(int $reviewId, int $status = 2): self
    {
        $this->addFieldToFilter('review_id', $reviewId);
        $this->addFieldToFilter('status', $status);
        $this->setOrder('created_at', self::SORT_ORDER_ASC);
        return $this;
    }
}
