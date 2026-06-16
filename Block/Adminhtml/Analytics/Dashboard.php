<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Block\Adminhtml\Analytics;

use ETechFlow\AdvancedProductReviews\Model\Analytics\StatsProvider;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Backing block for the Review Analytics Dashboard template.
 *
 * Pulls aggregates from {@see StatsProvider} and exposes them both as PHP
 * arrays (for KPI cards) and JSON (for the dependency-free charts).
 */
class Dashboard extends Template
{
    /**
     * @var array<string,mixed>|null
     */
    private ?array $data = null;

    /**
     * @param Context $context
     * @param StatsProvider $statsProvider
     * @param ProductRepositoryInterface $productRepository
     * @param Json $json
     * @param array $blockData
     */
    public function __construct(
        Context $context,
        private readonly StatsProvider $statsProvider,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Json $json,
        array $blockData = []
    ) {
        parent::__construct($context, $blockData);
    }

    /**
     * Lazily compute and memoise the dashboard dataset.
     *
     * Note: deliberately NOT named getData() — overriding DataObject::getData()
     * would break the framework's internal block data access during rendering.
     *
     * @return array<string,mixed>
     */
    public function getDashboardData(): array
    {
        if ($this->data === null) {
            $this->data = $this->statsProvider->getDashboardData();
        }
        return $this->data;
    }

    /**
     * @return array<string,int|float>
     */
    public function getKpis(): array
    {
        return $this->getDashboardData()['kpis'];
    }

    /**
     * Rating distribution as JSON for the bar chart: [{star, count}].
     *
     * @return string
     */
    public function getRatingDistributionJson(): string
    {
        $payload = [];
        foreach ($this->getDashboardData()['rating_distribution'] as $star => $count) {
            $payload[] = ['star' => $star, 'count' => $count];
        }
        return $this->json->serialize($payload);
    }

    /**
     * Monthly trend as JSON for the line chart: [{label, count}].
     *
     * @return string
     */
    public function getTrendJson(): string
    {
        $payload = [];
        foreach ($this->getDashboardData()['trend'] as $label => $count) {
            $payload[] = ['label' => $label, 'count' => $count];
        }
        return $this->json->serialize($payload);
    }

    /**
     * Top products with resolved names for the table.
     *
     * @return array<int,array{name:string,product_id:int,count:int}>
     */
    public function getTopProducts(): array
    {
        $rows = [];
        foreach ($this->getDashboardData()['top_products'] as $row) {
            $rows[] = [
                'product_id' => $row['product_id'],
                'count' => $row['count'],
                'name' => $this->resolveProductName($row['product_id']),
            ];
        }
        return $rows;
    }

    /**
     * Resolve a product name, degrading gracefully if it has been deleted.
     *
     * @param int $productId
     * @return string
     */
    private function resolveProductName(int $productId): string
    {
        try {
            return (string) $this->productRepository->getById($productId)->getName();
        } catch (\Exception $e) {
            return (string) __('Product #%1', $productId);
        }
    }
}
