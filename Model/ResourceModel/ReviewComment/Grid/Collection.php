<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewComment\Grid;

use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewComment as ReviewCommentResource;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Grid (UI listing) collection for review comments.
 */
class Collection extends SearchResult
{
    /**
     * @inheritDoc
     */
    protected function _initSelect(): void
    {
        parent::_initSelect();
        // Join the core review detail to surface the product/nickname context.
        $this->getSelect()->joinLeft(
            ['rd' => $this->getTable('review_detail')],
            'main_table.review_id = rd.review_id',
            ['review_nickname' => 'rd.nickname', 'review_title' => 'rd.title']
        );
    }
}
