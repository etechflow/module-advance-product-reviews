<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Resolver;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;

/**
 * Resolves a product entity id from GraphQL args that provide either a `sku`
 * or a `productId`. Shared by every resolver that targets a product.
 */
class ProductLocator
{
    /**
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * @param array<string,mixed> $args
     * @return int
     * @throws GraphQlInputException
     * @throws GraphQlNoSuchEntityException
     */
    public function resolveId(array $args): int
    {
        if (!empty($args['productId'])) {
            return (int) $args['productId'];
        }
        if (!empty($args['product_id'])) {
            return (int) $args['product_id'];
        }
        if (!empty($args['sku'])) {
            try {
                return (int) $this->productRepository->get((string) $args['sku'])->getId();
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                throw new GraphQlNoSuchEntityException(
                    __('No product found with SKU "%1".', $args['sku'])
                );
            }
        }
        throw new GraphQlInputException(__('Provide either "sku" or "productId".'));
    }
}
