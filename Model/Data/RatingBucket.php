<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Data;

use ETechFlow\AdvancedProductReviews\Api\Data\RatingBucketInterface;
use Magento\Framework\Api\AbstractSimpleObject;

/**
 * @inheritDoc
 */
class RatingBucket extends AbstractSimpleObject implements RatingBucketInterface
{
    /**
     * @inheritDoc
     */
    public function getRating(): int
    {
        return (int) $this->_get(self::RATING);
    }

    /**
     * @inheritDoc
     */
    public function setRating(int $rating): RatingBucketInterface
    {
        return $this->setData(self::RATING, $rating);
    }

    /**
     * @inheritDoc
     */
    public function getCount(): int
    {
        return (int) $this->_get(self::COUNT);
    }

    /**
     * @inheritDoc
     */
    public function setCount(int $count): RatingBucketInterface
    {
        return $this->setData(self::COUNT, $count);
    }

    /**
     * @inheritDoc
     */
    public function getPercent(): int
    {
        return (int) $this->_get(self::PERCENT);
    }

    /**
     * @inheritDoc
     */
    public function setPercent(int $percent): RatingBucketInterface
    {
        return $this->setData(self::PERCENT, $percent);
    }
}
