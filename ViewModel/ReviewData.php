<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\ViewModel;

use ETechFlow\AdvancedProductReviews\Api\ReviewExtraRepositoryInterface;
use ETechFlow\AdvancedProductReviews\Model\Config;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewComment\CollectionFactory as CommentCollectionFactory;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewMedia\CollectionFactory as MediaCollectionFactory;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\Question\CollectionFactory as QuestionCollectionFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Storefront ViewModel exposing all review/Q&A data and config to templates.
 */
class ReviewData implements ArgumentInterface
{
    /**
     * @param Config $config
     * @param Registry $registry
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManager
     * @param ReviewExtraRepositoryInterface $extraRepository
     * @param MediaCollectionFactory $mediaCollectionFactory
     * @param CommentCollectionFactory $commentCollectionFactory
     * @param QuestionCollectionFactory $questionCollectionFactory
     */
    public function __construct(
        private readonly Config $config,
        private readonly Registry $registry,
        private readonly CustomerSession $customerSession,
        private readonly StoreManagerInterface $storeManager,
        private readonly ReviewExtraRepositoryInterface $extraRepository,
        private readonly MediaCollectionFactory $mediaCollectionFactory,
        private readonly CommentCollectionFactory $commentCollectionFactory,
        private readonly QuestionCollectionFactory $questionCollectionFactory
    ) {
    }

    /**
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * Generic store-scoped config flag reader for templates.
     *
     * @param string $path
     * @return bool
     */
    public function isFlag(string $path): bool
    {
        return $this->config->isFlag($path);
    }

    /**
     * Whether Claude auto-translation is enabled for the current store.
     *
     * @return bool
     */
    public function isTranslationEnabled(): bool
    {
        return $this->config->isTranslationEnabled($this->getCurrentStoreId());
    }

    /**
     * Current product from the catalog registry.
     *
     * @return ProductInterface|null
     */
    public function getCurrentProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof ProductInterface ? $product : null;
    }

    /**
     * @return int
     */
    public function getCurrentProductId(): int
    {
        $product = $this->getCurrentProduct();
        return $product ? (int) $product->getId() : 0;
    }

    /**
     * @return int
     */
    public function getCurrentStoreId(): int
    {
        try {
            return (int) $this->storeManager->getStore()->getId();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Whether guests are allowed to submit reviews.
     *
     * @return bool
     */
    public function isGuestReviewsAllowed(): bool
    {
        return $this->config->isGuestReviewsAllowed();
    }

    /**
     * Whether a purchase is required before a review can be submitted.
     *
     * @return bool
     */
    public function isPurchaseRequired(): bool
    {
        return $this->config->isPurchaseRequired();
    }

    /**
     * Whether the current visitor is permitted to see/use the review form,
     * given the guest policy. (Purchase verification itself is enforced
     * server-side on submit.)
     *
     * @return bool
     */
    public function canCurrentVisitorReview(): bool
    {
        return $this->isLoggedIn() || $this->isGuestReviewsAllowed();
    }

    /**
     * Extra data (pros/cons, recommend, verified, helpful counts) for a review.
     *
     * @param int $reviewId
     * @return \ETechFlow\AdvancedProductReviews\Api\Data\ReviewExtraInterface
     */
    public function getExtra(int $reviewId)
    {
        return $this->extraRepository->getByReviewId($reviewId);
    }

    /**
     * Split a pros/cons text blob (one item per line) into an array.
     *
     * @param string|null $blob
     * @return string[]
     */
    public function splitLines(?string $blob): array
    {
        if (!$blob) {
            return [];
        }
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $blob))));
    }

    /**
     * Media items attached to a review, ordered for display.
     *
     * @param int $reviewId
     * @return \ETechFlow\AdvancedProductReviews\Model\ReviewMedia[]
     */
    public function getMedia(int $reviewId): array
    {
        $collection = $this->mediaCollectionFactory->create()->addReviewFilter($reviewId);
        return array_values($collection->getItems());
    }

    /**
     * Approved comments for a review.
     *
     * @param int $reviewId
     * @return \ETechFlow\AdvancedProductReviews\Model\ReviewComment[]
     */
    public function getComments(int $reviewId): array
    {
        $collection = $this->commentCollectionFactory->create()->addReviewFilter($reviewId);
        return array_values($collection->getItems());
    }

    /**
     * Approved questions for the current product.
     *
     * @return \ETechFlow\AdvancedProductReviews\Model\Question[]
     */
    public function getQuestions(): array
    {
        $productId = $this->getCurrentProductId();
        if ($productId === 0) {
            return [];
        }
        $collection = $this->questionCollectionFactory->create()
            ->addProductFilter($productId, $this->getCurrentStoreId());
        return array_values($collection->getItems());
    }

    /**
     * Build the public media URL for a stored relative path.
     *
     * @param string $relativePath
     * @return string
     */
    public function getMediaUrl(string $relativePath): string
    {
        try {
            $base = $this->storeManager->getStore()
                ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
            return $base . ltrim($relativePath, '/');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * URL of the AJAX endpoint for a given action path.
     *
     * @param string $path e.g. "vote/save"
     * @return string
     */
    public function getActionUrl(string $path): string
    {
        try {
            return $this->storeManager->getStore()->getUrl('etechflow_reviews/' . $path);
        } catch (\Exception $e) {
            return '';
        }
    }
}
