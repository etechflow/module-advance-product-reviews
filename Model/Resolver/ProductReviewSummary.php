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
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Resolves the `etf_review_summary` field added to ProductInterface, so a
 * products query can pull the summary inline without a second round-trip.
 */
class ProductReviewSummary implements ResolverInterface
{
    /**
     * @param ReviewSummaryProvider $summaryProvider
     */
    public function __construct(
        private readonly ReviewSummaryProvider $summaryProvider
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        if (!isset($value['model'])) {
            throw new GraphQlInputException(__('"model" value should be specified.'));
        }
        /** @var \Magento\Catalog\Api\Data\ProductInterface $product */
        $product = $value['model'];

        $store = $context->getExtensionAttributes()->getStore();
        $storeId = $store instanceof StoreInterface ? (int) $store->getId() : 0;

        return $this->summaryProvider->getForProduct((int) $product->getId(), $storeId);
    }
}
