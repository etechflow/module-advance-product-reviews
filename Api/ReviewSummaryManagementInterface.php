<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Api;

use ETechFlow\AdvancedProductReviews\Api\Data\ReviewSummaryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Read-only REST access to a product's aggregated review summary.
 *
 * @api
 */
interface ReviewSummaryManagementInterface
{
    /**
     * Get the review summary for a product by entity id.
     *
     * @param int $productId
     * @param int|null $storeId Defaults to the current store.
     * @return ReviewSummaryInterface
     */
    public function getSummary(int $productId, ?int $storeId = null): ReviewSummaryInterface;

    /**
     * Get the review summary for a product by SKU.
     *
     * @param string $sku
     * @param int|null $storeId Defaults to the current store.
     * @return ReviewSummaryInterface
     * @throws NoSuchEntityException
     */
    public function getSummaryBySku(string $sku, ?int $storeId = null): ReviewSummaryInterface;
}
