<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Data;

use ETechFlow\AdvancedProductReviews\Api\Data\ReviewSummaryInterface;
use Magento\Framework\Api\AbstractSimpleObject;

/**
 * @inheritDoc
 */
class ReviewSummary extends AbstractSimpleObject implements ReviewSummaryInterface
{
    /**
     * @inheritDoc
     */
    public function getProductId(): int
    {
        return (int) $this->_get(self::PRODUCT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setProductId(int $productId): ReviewSummaryInterface
    {
        return $this->setData(self::PRODUCT_ID, $productId);
    }

    /**
     * @inheritDoc
     */
    public function getReviewCount(): int
    {
        return (int) $this->_get(self::REVIEW_COUNT);
    }

    /**
     * @inheritDoc
     */
    public function setReviewCount(int $count): ReviewSummaryInterface
    {
        return $this->setData(self::REVIEW_COUNT, $count);
    }

    /**
     * @inheritDoc
     */
    public function getAverageRating(): float
    {
        return (float) $this->_get(self::AVERAGE_RATING);
    }

    /**
     * @inheritDoc
     */
    public function setAverageRating(float $rating): ReviewSummaryInterface
    {
        return $this->setData(self::AVERAGE_RATING, $rating);
    }

    /**
     * @inheritDoc
     */
    public function getRecommendPercent(): int
    {
        return (int) $this->_get(self::RECOMMEND_PERCENT);
    }

    /**
     * @inheritDoc
     */
    public function setRecommendPercent(int $percent): ReviewSummaryInterface
    {
        return $this->setData(self::RECOMMEND_PERCENT, $percent);
    }

    /**
     * @inheritDoc
     */
    public function getVerifiedCount(): int
    {
        return (int) $this->_get(self::VERIFIED_COUNT);
    }

    /**
     * @inheritDoc
     */
    public function setVerifiedCount(int $count): ReviewSummaryInterface
    {
        return $this->setData(self::VERIFIED_COUNT, $count);
    }

    /**
     * @inheritDoc
     */
    public function getWithMediaCount(): int
    {
        return (int) $this->_get(self::WITH_MEDIA_COUNT);
    }

    /**
     * @inheritDoc
     */
    public function setWithMediaCount(int $count): ReviewSummaryInterface
    {
        return $this->setData(self::WITH_MEDIA_COUNT, $count);
    }

    /**
     * @inheritDoc
     */
    public function getRatingDistribution(): array
    {
        return (array) $this->_get(self::RATING_DISTRIBUTION);
    }

    /**
     * @inheritDoc
     */
    public function setRatingDistribution(array $buckets): ReviewSummaryInterface
    {
        return $this->setData(self::RATING_DISTRIBUTION, $buckets);
    }
}
