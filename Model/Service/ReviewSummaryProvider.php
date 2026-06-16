<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Service;

use ETechFlow\AdvancedProductReviews\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Review\Model\Review;

/**
 * Per-product review summary (rating average, star distribution, recommend %,
 * verified count, media count).
 *
 * Computed with grouped SQL so it stays fast and is shared by both the GraphQL
 * resolvers and the REST endpoints — one source of truth for headless clients.
 */
class ReviewSummaryProvider
{
    /**
     * @param ResourceConnection $resource
     * @param Config $config
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Config $config
    ) {
    }

    /**
     * Build the summary payload for a single product.
     *
     * @param int $productId
     * @param int $storeId
     * @return array<string,mixed>
     */
    public function getForProduct(int $productId, int $storeId): array
    {
        // License/enable gate — no headless data when the module is off or unlicensed.
        if (!$this->config->isEnabled()) {
            return [
                'product_id' => $productId,
                'review_count' => 0,
                'average_rating' => 0.0,
                'recommend_percent' => 0,
                'verified_count' => 0,
                'with_media_count' => 0,
                'rating_distribution' => $this->emptyDistribution(),
            ];
        }

        $connection = $this->resource->getConnection();

        // review_ids of APPROVED product reviews visible in this store.
        $reviewIdsSelect = $connection->select()
            ->from(['r' => $this->resource->getTableName('review')], ['r.review_id'])
            ->join(
                ['re' => $this->resource->getTableName('review_entity')],
                're.entity_id = r.entity_id',
                []
            )
            ->join(
                ['rs' => $this->resource->getTableName('review_store')],
                'rs.review_id = r.review_id',
                []
            )
            ->where('re.entity_code = ?', Review::ENTITY_PRODUCT_CODE)
            ->where('r.entity_pk_value = ?', $productId)
            ->where('r.status_id = ?', Review::STATUS_APPROVED)
            ->where('rs.store_id IN (?)', [$storeId, 0])
            ->group('r.review_id');

        $reviewIds = $connection->fetchCol($reviewIdsSelect);
        $totalCount = count($reviewIds);

        $empty = [
            'product_id' => $productId,
            'review_count' => 0,
            'average_rating' => 0.0,
            'recommend_percent' => 0,
            'verified_count' => 0,
            'with_media_count' => 0,
            'rating_distribution' => $this->emptyDistribution(),
        ];
        if ($totalCount === 0) {
            return $empty;
        }

        // Per-review average star (0-5), then distribution + overall average.
        $perReview = $connection->select()
            ->from(
                $this->resource->getTableName('rating_option_vote'),
                ['review_id', 'stars' => new \Zend_Db_Expr('AVG(percent) / 20')]
            )
            ->where('review_id IN (?)', $reviewIds)
            ->group('review_id');

        $distribution = $this->emptyDistribution();
        $sum = 0.0;
        $rated = 0;
        foreach ($connection->fetchAll($perReview) as $row) {
            $stars = (float) $row['stars'];
            $sum += $stars;
            $rated++;
            $bucket = (int) round($stars);
            if ($bucket >= 1 && $bucket <= 5) {
                $distribution[$bucket]['count']++;
            }
        }
        $average = $rated > 0 ? round($sum / $rated, 2) : 0.0;

        foreach ($distribution as &$entry) {
            $entry['percent'] = $rated > 0 ? (int) round(($entry['count'] / $rated) * 100) : 0;
        }
        unset($entry);

        // Extra-table driven figures (recommend / verified).
        $extraStats = $connection->fetchRow(
            $connection->select()->from(
                $this->resource->getTableName('etechflow_review_extra'),
                [
                    'cnt' => 'COUNT(*)',
                    'recommended' => new \Zend_Db_Expr('SUM(is_recommended)'),
                    'verified' => new \Zend_Db_Expr('SUM(is_verified_buyer)'),
                ]
            )->where('review_id IN (?)', $reviewIds)
        ) ?: [];
        $extraTotal = (int) ($extraStats['cnt'] ?? 0);

        $withMedia = (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('etechflow_review_media'), [new \Zend_Db_Expr('COUNT(DISTINCT review_id)')])
                ->where('review_id IN (?)', $reviewIds)
        );

        return [
            'product_id' => $productId,
            'review_count' => $totalCount,
            'average_rating' => $average,
            'recommend_percent' => $extraTotal > 0
                ? (int) round(((int) $extraStats['recommended'] / $extraTotal) * 100)
                : 0,
            'verified_count' => (int) ($extraStats['verified'] ?? 0),
            'with_media_count' => $withMedia,
            'rating_distribution' => array_values($distribution),
        ];
    }

    /**
     * Zeroed 5..1 star buckets.
     *
     * @return array<int,array{rating:int,count:int,percent:int}>
     */
    private function emptyDistribution(): array
    {
        $dist = [];
        for ($star = 5; $star >= 1; $star--) {
            $dist[$star] = ['rating' => $star, 'count' => 0, 'percent' => 0];
        }
        return $dist;
    }
}
