<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Resolver;

use ETechFlow\AdvancedProductReviews\Model\Service\ReviewSummaryProvider;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Resolves the top-level `etfReviewSummary` query.
 */
class ReviewSummary implements ResolverInterface
{
    /**
     * @param ProductLocator $productLocator
     * @param ReviewSummaryProvider $summaryProvider
     */
    public function __construct(
        private readonly ProductLocator $productLocator,
        private readonly ReviewSummaryProvider $summaryProvider
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $productId = $this->productLocator->resolveId($args ?? []);
        $store = $context->getExtensionAttributes()->getStore();
        $storeId = $store instanceof StoreInterface ? (int) $store->getId() : 0;

        return $this->summaryProvider->getForProduct($productId, $storeId);
    }
}
