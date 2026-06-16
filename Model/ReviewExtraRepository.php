<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model;

use ETechFlow\AdvancedProductReviews\Api\Data\ReviewExtraInterface;
use ETechFlow\AdvancedProductReviews\Api\Data\ReviewExtraInterfaceFactory;
use ETechFlow\AdvancedProductReviews\Api\Data\ReviewExtraSearchResultsInterface;
use ETechFlow\AdvancedProductReviews\Api\Data\ReviewExtraSearchResultsInterfaceFactory;
use ETechFlow\AdvancedProductReviews\Api\ReviewExtraRepositoryInterface;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewExtra as ReviewExtraResource;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewExtra\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Repository for extra review data.
 */
class ReviewExtraRepository implements ReviewExtraRepositoryInterface
{
    /**
     * @param ReviewExtraResource $resource
     * @param ReviewExtraFactory $reviewExtraFactory
     * @param ReviewExtraInterfaceFactory $dataFactory
     * @param CollectionFactory $collectionFactory
     * @param ReviewExtraSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly ReviewExtraResource $resource,
        private readonly ReviewExtraFactory $reviewExtraFactory,
        private readonly ReviewExtraInterfaceFactory $dataFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly ReviewExtraSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(ReviewExtraInterface $reviewExtra): ReviewExtraInterface
    {
        try {
            $this->resource->save($reviewExtra);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the review extra data: %1', $exception->getMessage()),
                $exception
            );
        }
        return $reviewExtra;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $extraId): ReviewExtraInterface
    {
        $reviewExtra = $this->reviewExtraFactory->create();
        $this->resource->load($reviewExtra, $extraId);
        if (!$reviewExtra->getExtraId()) {
            throw new NoSuchEntityException(
                __('Review extra data with id "%1" does not exist.', $extraId)
            );
        }
        return $reviewExtra;
    }

    /**
     * @inheritDoc
     */
    public function getByReviewId(int $reviewId): ReviewExtraInterface
    {
        $reviewExtra = $this->reviewExtraFactory->create();
        $this->resource->load($reviewExtra, $reviewId, ReviewExtraInterface::REVIEW_ID);
        if (!$reviewExtra->getExtraId()) {
            // Return a fresh, unsaved entity bound to this review so callers
            // can read defaults without null checks.
            $reviewExtra->setReviewId($reviewId);
        }
        return $reviewExtra;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ReviewExtraSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var ReviewExtraSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(ReviewExtraInterface $reviewExtra): bool
    {
        try {
            $this->resource->delete($reviewExtra);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the review extra data: %1', $exception->getMessage()),
                $exception
            );
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $extraId): bool
    {
        return $this->delete($this->getById($extraId));
    }
}
