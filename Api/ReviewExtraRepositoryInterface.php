<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Api;

use ETechFlow\AdvancedProductReviews\Api\Data\ReviewExtraInterface;
use ETechFlow\AdvancedProductReviews\Api\Data\ReviewExtraSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Repository for ReviewExtra entities.
 *
 * @api
 */
interface ReviewExtraRepositoryInterface
{
    /**
     * Save extra review data.
     *
     * @param ReviewExtraInterface $reviewExtra
     * @return ReviewExtraInterface
     * @throws CouldNotSaveException
     */
    public function save(ReviewExtraInterface $reviewExtra): ReviewExtraInterface;

    /**
     * Load by primary key.
     *
     * @param int $extraId
     * @return ReviewExtraInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $extraId): ReviewExtraInterface;

    /**
     * Load (or create empty) extra row by core review id.
     *
     * @param int $reviewId
     * @return ReviewExtraInterface
     */
    public function getByReviewId(int $reviewId): ReviewExtraInterface;

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return ReviewExtraSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ReviewExtraSearchResultsInterface;

    /**
     * @param ReviewExtraInterface $reviewExtra
     * @return bool
     */
    public function delete(ReviewExtraInterface $reviewExtra): bool;

    /**
     * @param int $extraId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function deleteById(int $extraId): bool;
}
