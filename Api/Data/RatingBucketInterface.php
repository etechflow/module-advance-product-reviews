<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Api\Data;

/**
 * One star bucket (1-5) within a product's rating distribution.
 *
 * @api
 */
interface RatingBucketInterface
{
    public const RATING = 'rating';
    public const COUNT = 'count';
    public const PERCENT = 'percent';

    /**
     * Star value, 1-5.
     *
     * @return int
     */
    public function getRating(): int;

    /**
     * @param int $rating
     * @return $this
     */
    public function setRating(int $rating): self;

    /**
     * Number of reviews in this bucket.
     *
     * @return int
     */
    public function getCount(): int;

    /**
     * @param int $count
     * @return $this
     */
    public function setCount(int $count): self;

    /**
     * Share of reviews in this bucket, 0-100.
     *
     * @return int
     */
    public function getPercent(): int;

    /**
     * @param int $percent
     * @return $this
     */
    public function setPercent(int $percent): self;
}
