<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewExtra;

use ETechFlow\AdvancedProductReviews\Model\ReviewExtra as ReviewExtraModel;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewExtra as ReviewExtraResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * ReviewExtra collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'extra_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ReviewExtraModel::class, ReviewExtraResource::class);
    }
}
