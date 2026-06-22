<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Block\Widget;

use ETechFlow\AdvancedProductReviews\Model\Config;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Review\Model\Review;
use Magento\Review\Model\ResourceModel\Review\Product\CollectionFactory as ReviewCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;

/**
 * Storefront widget: renders customer reviews on any page.
 *
 * Modes:
 *   - latest  : the newest approved reviews across the whole store
 *   - product : approved reviews for one product (by SKU)
 */
class Reviews extends Template implements BlockInterface
{
    /** @var string */
    protected $_template = 'ETechFlow_AdvancedProductReviews::widget/reviews.phtml';

    /**
     * @param Context $context
     * @param ReviewCollectionFactory $reviewCollectionFactory
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly ReviewCollectionFactory $reviewCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return bool
     */
    public function isModuleEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * @return string
     */
    public function getHeading(): string
    {
        return trim((string) ($this->getData('title') ?? ''));
    }

    /**
     * @return bool
     */
    public function showProductName(): bool
    {
        return (bool) ((int) $this->getData('show_product_name'));
    }

    /**
     * Resolve the reviews to render based on the widget parameters.
     *
     * @return array[]
     */
    public function getReviewItems(): array
    {
        if (!$this->config->isEnabled()) {
            return [];
        }
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
        } catch (\Exception $e) {
            return [];
        }

        $limit     = max(1, (int) ($this->getData('limit') ?: 5));
        $minRating = (int) $this->getData('min_rating');
        $mode      = (string) ($this->getData('display_mode') ?: 'latest');

        $productId = 0;
        if ($mode === 'product') {
            $sku = trim((string) $this->getData('product_sku'));
            if ($sku === '') {
                return [];
            }
            try {
                $productId = (int) $this->productRepository->get($sku)->getId();
            } catch (\Exception $e) {
                return [];
            }
        }

        $collection = $this->reviewCollectionFactory->create()
            ->addStoreFilter($storeId)
            ->addStatusFilter(Review::STATUS_APPROVED)
            ->setDateOrder()
            ->addRateVotes();

        if ($productId > 0) {
            $collection->addEntityFilter('product', $productId);
        }

        // Over-fetch when filtering by rating so the limit can still be filled.
        $collection->setPageSize($minRating > 0 ? $limit * 4 : $limit)->setCurPage(1);

        $items = [];
        foreach ($collection as $review) {
            $stars = $this->resolveStars($review);
            if ($minRating > 0 && $stars < $minRating) {
                continue;
            }
            $item = [
                'nickname' => (string) $review->getNickname(),
                'title'    => (string) $review->getTitle(),
                'detail'   => (string) $review->getDetail(),
                'stars'    => $stars,
                'date'     => $this->formatDate(
                    $review->getCreatedAt(),
                    \IntlDateFormatter::MEDIUM
                ),
                'product_name' => '',
                'product_url'  => '',
            ];
            if ($this->showProductName()) {
                [$item['product_name'], $item['product_url']] =
                    $this->resolveProduct((int) $review->getEntityPkValue());
            }
            $items[] = $item;
            if (count($items) >= $limit) {
                break;
            }
        }
        return $items;
    }

    /**
     * Average star rating (0-5) for a review from its rating votes.
     *
     * @param \Magento\Review\Model\Review $review
     * @return int
     */
    private function resolveStars($review): int
    {
        $votes = $review->getRatingVotes();
        if (!$votes || !count($votes)) {
            return 0;
        }
        $sum = 0;
        $n = 0;
        foreach ($votes as $vote) {
            $sum += (int) $vote->getPercent();
            $n++;
        }
        return $n > 0 ? (int) round(($sum / $n) / 20) : 0;
    }

    /**
     * @param int $productId
     * @return array{0:string,1:string} [name, url]
     */
    private function resolveProduct(int $productId): array
    {
        if ($productId <= 0) {
            return ['', ''];
        }
        try {
            $product = $this->productRepository->getById($productId);
            return [(string) $product->getName(), (string) $product->getProductUrl()];
        } catch (\Exception $e) {
            return ['', ''];
        }
    }

    /**
     * Vary the block cache by the widget parameters.
     *
     * @return array
     */
    public function getCacheKeyInfo()
    {
        return array_merge(parent::getCacheKeyInfo(), [
            'ETECHFLOW_REVIEWS_WIDGET',
            (string) $this->getData('display_mode'),
            (string) $this->getData('product_sku'),
            (string) $this->getData('limit'),
            (string) $this->getData('min_rating'),
            (string) $this->getData('show_product_name'),
            (string) $this->getData('title'),
        ]);
    }
}
