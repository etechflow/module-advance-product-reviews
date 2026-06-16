<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Analytics;

use Magento\Framework\App\ResourceConnection;
use Magento\Review\Model\Review;

/**
 * Aggregates review data for the admin analytics dashboard.
 *
 * All figures are computed with grouped SQL against the core review tables and
 * this module's extra/media tables, so the dashboard stays fast on large
 * catalogs. Product review rows are isolated via the review_entity join.
 */
class StatsProvider
{
    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Build the full dashboard dataset.
     *
     * @return array<string,mixed>
     */
    public function getDashboardData(): array
    {
        return [
            'kpis' => $this->getKpis(),
            'rating_distribution' => $this->getRatingDistribution(),
            'trend' => $this->getMonthlyTrend(12),
            'top_products' => $this->getTopProducts(5),
        ];
    }

    /**
     * Headline KPI counters.
     *
     * @return array<string,int|float>
     */
    public function getKpis(): array
    {
        $connection = $this->resource->getConnection();
        $reviewTable = $this->resource->getTableName('review');
        $extraTable = $this->resource->getTableName('etechflow_review_extra');
        $mediaTable = $this->resource->getTableName('etechflow_review_media');
        $voteTable = $this->resource->getTableName('rating_option_vote');

        $statusSelect = $connection->select()
            ->from(
                ['r' => $reviewTable],
                [
                    'total' => 'COUNT(*)',
                    'approved' => new \Zend_Db_Expr('SUM(CASE WHEN r.status_id = ' . Review::STATUS_APPROVED . ' THEN 1 ELSE 0 END)'),
                    'pending' => new \Zend_Db_Expr('SUM(CASE WHEN r.status_id = ' . Review::STATUS_PENDING . ' THEN 1 ELSE 0 END)'),
                    'rejected' => new \Zend_Db_Expr('SUM(CASE WHEN r.status_id = ' . Review::STATUS_NOT_APPROVED . ' THEN 1 ELSE 0 END)'),
                ]
            );
        $status = $connection->fetchRow($statusSelect) ?: [];

        // Average rating across all rating votes (percent 0-100 -> 0-5 stars).
        $avgPercent = (float) $connection->fetchOne(
            $connection->select()->from($voteTable, [new \Zend_Db_Expr('AVG(percent)')])
        );

        // Extra-row driven rates.
        $extraStats = $connection->fetchRow(
            $connection->select()->from(
                $extraTable,
                [
                    'extra_total' => 'COUNT(*)',
                    'recommended' => new \Zend_Db_Expr('SUM(is_recommended)'),
                    'verified' => new \Zend_Db_Expr('SUM(is_verified_buyer)'),
                    'helpful' => new \Zend_Db_Expr('SUM(helpful_count)'),
                ]
            )
        ) ?: [];

        $reviewsWithMedia = (int) $connection->fetchOne(
            $connection->select()->from($mediaTable, [new \Zend_Db_Expr('COUNT(DISTINCT review_id)')])
        );
        $videoCount = (int) $connection->fetchOne(
            $connection->select()
                ->from($mediaTable, [new \Zend_Db_Expr('COUNT(*)')])
                ->where('media_type = ?', 'video')
        );

        $total = (int) ($status['total'] ?? 0);
        $extraTotal = (int) ($extraStats['extra_total'] ?? 0);

        return [
            'total' => $total,
            'approved' => (int) ($status['approved'] ?? 0),
            'pending' => (int) ($status['pending'] ?? 0),
            'rejected' => (int) ($status['rejected'] ?? 0),
            'avg_rating' => round($avgPercent / 20, 2),
            'recommend_rate' => $extraTotal ? (int) round(((int) $extraStats['recommended'] / $extraTotal) * 100) : 0,
            'verified_rate' => $extraTotal ? (int) round(((int) $extraStats['verified'] / $extraTotal) * 100) : 0,
            'helpful_votes' => (int) ($extraStats['helpful'] ?? 0),
            'with_media' => $reviewsWithMedia,
            'video_count' => $videoCount,
        ];
    }

    /**
     * Count of reviews per star bucket (1-5).
     *
     * @return array<int,int> Star (1..5) => review count
     */
    public function getRatingDistribution(): array
    {
        $connection = $this->resource->getConnection();
        $voteTable = $this->resource->getTableName('rating_option_vote');

        // Average each review's votes, round to a star bucket, then count.
        $perReview = $connection->select()
            ->from($voteTable, ['review_id', 'stars' => new \Zend_Db_Expr('ROUND(AVG(percent) / 20)')])
            ->group('review_id');

        $select = $connection->select()
            ->from(['t' => $perReview], ['stars', 'cnt' => 'COUNT(*)'])
            ->group('stars');

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($connection->fetchAll($select) as $row) {
            $star = (int) $row['stars'];
            if ($star >= 1 && $star <= 5) {
                $distribution[$star] = (int) $row['cnt'];
            }
        }
        return $distribution;
    }

    /**
     * Reviews created per month for the last N months (oldest first).
     *
     * @param int $months
     * @return array<string,int> "YYYY-MM" => count
     */
    public function getMonthlyTrend(int $months = 12): array
    {
        $connection = $this->resource->getConnection();
        $reviewTable = $this->resource->getTableName('review');

        $select = $connection->select()
            ->from(
                $reviewTable,
                [
                    'ym' => new \Zend_Db_Expr("DATE_FORMAT(created_at, '%Y-%m')"),
                    'cnt' => 'COUNT(*)',
                ]
            )
            ->where('created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)', $months)
            ->group('ym')
            ->order('ym ASC');

        $rows = $connection->fetchPairs($select);

        // Fill gaps so the chart has a continuous axis.
        $trend = [];
        $cursor = new \DateTime('first day of this month');
        $cursor->modify('-' . ($months - 1) . ' months');
        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');
            $trend[$key] = (int) ($rows[$key] ?? 0);
            $cursor->modify('+1 month');
        }
        return $trend;
    }

    /**
     * Products with the most reviews.
     *
     * @param int $limit
     * @return array<int,array{product_id:int,count:int}>
     */
    public function getTopProducts(int $limit = 5): array
    {
        $connection = $this->resource->getConnection();
        $reviewTable = $this->resource->getTableName('review');
        $entityTable = $this->resource->getTableName('review_entity');

        $select = $connection->select()
            ->from(['r' => $reviewTable], ['product_id' => 'r.entity_pk_value', 'count' => 'COUNT(*)'])
            ->join(['re' => $entityTable], 're.entity_id = r.entity_id', [])
            ->where('re.entity_code = ?', 'product')
            ->group('r.entity_pk_value')
            ->order('count DESC')
            ->limit($limit);

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[] = [
                'product_id' => (int) $row['product_id'],
                'count' => (int) $row['count'],
            ];
        }
        return $result;
    }
}
