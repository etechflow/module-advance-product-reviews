<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Search results container for ReviewExtra.
 *
 * @api
 */
interface ReviewExtraSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \ETechFlow\AdvancedProductReviews\Api\Data\ReviewExtraInterface[]
     */
    public function getItems();

    /**
     * @param \ETechFlow\AdvancedProductReviews\Api\Data\ReviewExtraInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
