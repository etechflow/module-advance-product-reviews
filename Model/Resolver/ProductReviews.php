<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Resolver;

use ETechFlow\AdvancedProductReviews\Model\Service\ReviewListProvider;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Resolves `etfProductReviews` — an enriched, paginated review list.
 */
class ProductReviews implements ResolverInterface
{
    private const SORT_MAP = [
        'RECENT' => 'recent',
        'OLDEST' => 'oldest',
        'HELPFUL' => 'helpful',
        'HIGHEST_RATING' => 'highest_rating',
        'LOWEST_RATING' => 'lowest_rating',
    ];

    /**
     * @param ProductLocator $productLocator
     * @param ReviewListProvider $listProvider
     */
    public function __construct(
        private readonly ProductLocator $productLocator,
        private readonly ReviewListProvider $listProvider
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $args = $args ?? [];
        $productId = $this->productLocator->resolveId($args);
        $storeId = $this->getStoreId($context);

        $sort = self::SORT_MAP[$args['sort'] ?? 'RECENT'] ?? 'recent';
        $filter = $args['filter'] ?? [];

        $data = $this->listProvider->getForProduct(
            $productId,
            $storeId,
            (int) ($args['pageSize'] ?? 10),
            (int) ($args['currentPage'] ?? 1),
            is_array($filter) ? $filter : [],
            $sort
        );

        return [
            'items' => $data['items'],
            'total_count' => $data['total_count'],
            'page_info' => [
                'current_page' => $data['current_page'],
                'page_size' => $data['page_size'],
                'total_pages' => $data['total_pages'],
            ],
        ];
    }

    /**
     * @param mixed $context
     * @return int
     */
    private function getStoreId($context): int
    {
        $store = $context->getExtensionAttributes()->getStore();
        return $store instanceof StoreInterface ? (int) $store->getId() : 0;
    }
}
