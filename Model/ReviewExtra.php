<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model;

use ETechFlow\AdvancedProductReviews\Api\Data\ReviewExtraInterface;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewExtra as ReviewExtraResource;
use Magento\Framework\Model\AbstractModel;

/**
 * ReviewExtra ActiveRecord model.
 */
class ReviewExtra extends AbstractModel implements ReviewExtraInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'etechflow_review_extra';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ReviewExtraResource::class);
    }

    /**
     * @inheritDoc
     */
    public function getExtraId(): ?int
    {
        $value = $this->getData(self::EXTRA_ID);
        return $value === null ? null : (int) $value;
    }

    /**
     * @inheritDoc
     */
    public function setExtraId(int $extraId): ReviewExtraInterface
    {
        return $this->setData(self::EXTRA_ID, $extraId);
    }

    /**
     * @inheritDoc
     */
    public function getReviewId(): int
    {
        return (int) $this->getData(self::REVIEW_ID);
    }

    /**
     * @inheritDoc
     */
    public function setReviewId(int $reviewId): ReviewExtraInterface
    {
        return $this->setData(self::REVIEW_ID, $reviewId);
    }

    /**
     * @inheritDoc
     */
    public function getPros(): ?string
    {
        $value = $this->getData(self::PROS);
        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritDoc
     */
    public function setPros(?string $pros): ReviewExtraInterface
    {
        return $this->setData(self::PROS, $pros);
    }

    /**
     * @inheritDoc
     */
    public function getCons(): ?string
    {
        $value = $this->getData(self::CONS);
        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritDoc
     */
    public function setCons(?string $cons): ReviewExtraInterface
    {
        return $this->setData(self::CONS, $cons);
    }

    /**
     * @inheritDoc
     */
    public function getIsRecommended(): bool
    {
        return (bool) $this->getData(self::IS_RECOMMENDED);
    }

    /**
     * @inheritDoc
     */
    public function setIsRecommended(bool $isRecommended): ReviewExtraInterface
    {
        return $this->setData(self::IS_RECOMMENDED, $isRecommended ? 1 : 0);
    }

    /**
     * @inheritDoc
     */
    public function getIsVerifiedBuyer(): bool
    {
        return (bool) $this->getData(self::IS_VERIFIED_BUYER);
    }

    /**
     * @inheritDoc
     */
    public function setIsVerifiedBuyer(bool $isVerifiedBuyer): ReviewExtraInterface
    {
        return $this->setData(self::IS_VERIFIED_BUYER, $isVerifiedBuyer ? 1 : 0);
    }

    /**
     * @inheritDoc
     */
    public function getHelpfulCount(): int
    {
        return (int) $this->getData(self::HELPFUL_COUNT);
    }

    /**
     * @inheritDoc
     */
    public function setHelpfulCount(int $count): ReviewExtraInterface
    {
        return $this->setData(self::HELPFUL_COUNT, $count);
    }

    /**
     * @inheritDoc
     */
    public function getNotHelpfulCount(): int
    {
        return (int) $this->getData(self::NOT_HELPFUL_COUNT);
    }

    /**
     * @inheritDoc
     */
    public function setNotHelpfulCount(int $count): ReviewExtraInterface
    {
        return $this->setData(self::NOT_HELPFUL_COUNT, $count);
    }

    /**
     * @inheritDoc
     */
    public function getOriginalLanguage(): ?string
    {
        $value = $this->getData(self::ORIGINAL_LANGUAGE);
        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritDoc
     */
    public function setOriginalLanguage(?string $language): ReviewExtraInterface
    {
        return $this->setData(self::ORIGINAL_LANGUAGE, $language);
    }
}
