<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\ResourceModel\Question\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Grid (UI listing) collection for product questions.
 */
class Collection extends SearchResult
{
    /**
     * @inheritDoc
     */
    protected function _initSelect(): void
    {
        parent::_initSelect();
        // Surface product name for the grid.
        $this->getSelect()->joinLeft(
            ['cpev' => $this->getTable('catalog_product_entity_varchar')],
            'main_table.product_id = cpev.entity_id AND cpev.attribute_id = ('
            . ' SELECT attribute_id FROM ' . $this->getTable('eav_attribute')
            . " WHERE attribute_code = 'name' AND entity_type_id ="
            . ' (SELECT entity_type_id FROM ' . $this->getTable('eav_entity_type')
            . " WHERE entity_type_code = 'catalog_product') LIMIT 1)",
            ['product_name' => 'cpev.value']
        );
    }
}
