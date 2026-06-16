<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewMedia;

use ETechFlow\AdvancedProductReviews\Model\ReviewMedia as ReviewMediaModel;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewMedia as ReviewMediaResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Review media collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'media_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ReviewMediaModel::class, ReviewMediaResource::class);
    }

    /**
     * Filter to media belonging to a given review, ordered for display.
     *
     * @param int $reviewId
     * @return $this
     */
    public function addReviewFilter(int $reviewId): self
    {
        $this->addFieldToFilter('review_id', $reviewId);
        $this->setOrder('sort_order', self::SORT_ORDER_ASC);
        return $this;
    }
}
