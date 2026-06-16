<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Api\Data;

/**
 * Aggregated review summary for a single product.
 *
 * @api
 */
interface ReviewSummaryInterface
{
    public const PRODUCT_ID = 'product_id';
    public const REVIEW_COUNT = 'review_count';
    public const AVERAGE_RATING = 'average_rating';
    public const RECOMMEND_PERCENT = 'recommend_percent';
    public const VERIFIED_COUNT = 'verified_count';
    public const WITH_MEDIA_COUNT = 'with_media_count';
    public const RATING_DISTRIBUTION = 'rating_distribution';

    /**
     * @return int
     */
    public function getProductId(): int;

    /**
     * @param int $productId
     * @return $this
     */
    public function setProductId(int $productId): self;

    /**
     * @return int
     */
    public function getReviewCount(): int;

    /**
     * @param int $count
     * @return $this
     */
    public function setReviewCount(int $count): self;

    /**
     * Average star rating, 0-5.
     *
     * @return float
     */
    public function getAverageRating(): float;

    /**
     * @param float $rating
     * @return $this
     */
    public function setAverageRating(float $rating): self;

    /**
     * Share of reviewers who recommend the product, 0-100.
     *
     * @return int
     */
    public function getRecommendPercent(): int;

    /**
     * @param int $percent
     * @return $this
     */
    public function setRecommendPercent(int $percent): self;

    /**
     * @return int
     */
    public function getVerifiedCount(): int;

    /**
     * @param int $count
     * @return $this
     */
    public function setVerifiedCount(int $count): self;

    /**
     * @return int
     */
    public function getWithMediaCount(): int;

    /**
     * @param int $count
     * @return $this
     */
    public function setWithMediaCount(int $count): self;

    /**
     * Star distribution, 5 down to 1.
     *
     * @return \ETechFlow\AdvancedProductReviews\Api\Data\RatingBucketInterface[]
     */
    public function getRatingDistribution(): array;

    /**
     * @param \ETechFlow\AdvancedProductReviews\Api\Data\RatingBucketInterface[] $buckets
     * @return $this
     */
    public function setRatingDistribution(array $buckets): self;
}
