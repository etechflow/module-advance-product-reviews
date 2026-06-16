<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Api\Data;

/**
 * Extra data attached to a core product review.
 *
 * @api
 */
interface ReviewExtraInterface
{
    public const EXTRA_ID = 'extra_id';
    public const REVIEW_ID = 'review_id';
    public const PROS = 'pros';
    public const CONS = 'cons';
    public const IS_RECOMMENDED = 'is_recommended';
    public const IS_VERIFIED_BUYER = 'is_verified_buyer';
    public const HELPFUL_COUNT = 'helpful_count';
    public const NOT_HELPFUL_COUNT = 'not_helpful_count';
    public const ORIGINAL_LANGUAGE = 'original_language';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * @return int|null
     */
    public function getExtraId(): ?int;

    /**
     * @param int $extraId
     * @return $this
     */
    public function setExtraId(int $extraId): self;

    /**
     * @return int
     */
    public function getReviewId(): int;

    /**
     * @param int $reviewId
     * @return $this
     */
    public function setReviewId(int $reviewId): self;

    /**
     * @return string|null
     */
    public function getPros(): ?string;

    /**
     * @param string|null $pros
     * @return $this
     */
    public function setPros(?string $pros): self;

    /**
     * @return string|null
     */
    public function getCons(): ?string;

    /**
     * @param string|null $cons
     * @return $this
     */
    public function setCons(?string $cons): self;

    /**
     * @return bool
     */
    public function getIsRecommended(): bool;

    /**
     * @param bool $isRecommended
     * @return $this
     */
    public function setIsRecommended(bool $isRecommended): self;

    /**
     * @return bool
     */
    public function getIsVerifiedBuyer(): bool;

    /**
     * @param bool $isVerifiedBuyer
     * @return $this
     */
    public function setIsVerifiedBuyer(bool $isVerifiedBuyer): self;

    /**
     * @return int
     */
    public function getHelpfulCount(): int;

    /**
     * @param int $count
     * @return $this
     */
    public function setHelpfulCount(int $count): self;

    /**
     * @return int
     */
    public function getNotHelpfulCount(): int;

    /**
     * @param int $count
     * @return $this
     */
    public function setNotHelpfulCount(int $count): self;

    /**
     * @return string|null
     */
    public function getOriginalLanguage(): ?string;

    /**
     * @param string|null $language
     * @return $this
     */
    public function setOriginalLanguage(?string $language): self;
}
