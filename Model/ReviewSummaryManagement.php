<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model;

use ETechFlow\AdvancedProductReviews\Api\Data\RatingBucketInterfaceFactory;
use ETechFlow\AdvancedProductReviews\Api\Data\ReviewSummaryInterface;
use ETechFlow\AdvancedProductReviews\Api\Data\ReviewSummaryInterfaceFactory;
use ETechFlow\AdvancedProductReviews\Api\ReviewSummaryManagementInterface;
use ETechFlow\AdvancedProductReviews\Model\Service\ReviewSummaryProvider;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @inheritDoc
 */
class ReviewSummaryManagement implements ReviewSummaryManagementInterface
{
    /**
     * @param ReviewSummaryProvider $summaryProvider
     * @param ReviewSummaryInterfaceFactory $summaryFactory
     * @param RatingBucketInterfaceFactory $bucketFactory
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ReviewSummaryProvider $summaryProvider,
        private readonly ReviewSummaryInterfaceFactory $summaryFactory,
        private readonly RatingBucketInterfaceFactory $bucketFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getSummary(int $productId, ?int $storeId = null): ReviewSummaryInterface
    {
        $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
        $data = $this->summaryProvider->getForProduct($productId, $storeId);
        return $this->build($data);
    }

    /**
     * @inheritDoc
     */
    public function getSummaryBySku(string $sku, ?int $storeId = null): ReviewSummaryInterface
    {
        $product = $this->productRepository->get($sku);
        return $this->getSummary((int) $product->getId(), $storeId);
    }

    /**
     * Convert the provider's array payload into typed DTOs.
     *
     * @param array<string,mixed> $data
     * @return ReviewSummaryInterface
     */
    private function build(array $data): ReviewSummaryInterface
    {
        $buckets = [];
        foreach ($data['rating_distribution'] as $bucket) {
            $buckets[] = $this->bucketFactory->create()
                ->setRating((int) $bucket['rating'])
                ->setCount((int) $bucket['count'])
                ->setPercent((int) $bucket['percent']);
        }

        return $this->summaryFactory->create()
            ->setProductId((int) $data['product_id'])
            ->setReviewCount((int) $data['review_count'])
            ->setAverageRating((float) $data['average_rating'])
            ->setRecommendPercent((int) $data['recommend_percent'])
            ->setVerifiedCount((int) $data['verified_count'])
            ->setWithMediaCount((int) $data['with_media_count'])
            ->setRatingDistribution($buckets);
    }
}
