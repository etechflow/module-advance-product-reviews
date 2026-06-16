<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewTranslation;

use ETechFlow\AdvancedProductReviews\Model\ReviewTranslation as ReviewTranslationModel;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewTranslation as ReviewTranslationResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Review translation collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'translation_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ReviewTranslationModel::class, ReviewTranslationResource::class);
    }
}
