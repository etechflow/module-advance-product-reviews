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
use Magento\Store\Model\StoreManagerInterface;

/**
 * Enriched, paginated review list for a single product.
 *
 * Returns the core review data (title/detail/nickname/rating) joined with this
 * module's extra data (pros/cons, recommend, verified, helpful counts), media,
 * and approved comments — in batched queries (no N+1). Shared by the GraphQL
 * `etfProductReviews` resolver and the REST endpoint.
 */
class ReviewListProvider
{
    private const SORT_RECENT = 'recent';
    private const SORT_OLDEST = 'oldest';
    private const SORT_HELPFUL = 'helpful';
    private const SORT_HIGHEST = 'highest_rating';
    private const SORT_LOWEST = 'lowest_rating';

    /**
     * @param ResourceConnection $resource
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    /**
     * @param int $productId
     * @param int $storeId
     * @param int $pageSize
     * @param int $currentPage
     * @param array<string,mixed> $filter Keys: rating (int), verified_only (bool), with_media (bool)
     * @param string|null $sort
     * @return array{items:array<int,array<string,mixed>>,total_count:int,current_page:int,page_size:int,total_pages:int}
     */
    public function getForProduct(
        int $productId,
        int $storeId,
        int $pageSize = 10,
        int $currentPage = 1,
        array $filter = [],
        ?string $sort = self::SORT_RECENT
    ): array {
        $pageSize = max(1, min($pageSize, 100));
        $currentPage = max(1, $currentPage);

        // License/enable gate — no headless data when the module is off or unlicensed.
        if (!$this->config->isEnabled()) {
            return [
                'items' => [],
                'total_count' => 0,
                'current_page' => $currentPage,
                'page_size' => $pageSize,
                'total_pages' => 0,
            ];
        }

        $connection = $this->resource->getConnection();

        $base = $this->buildBaseSelect($productId, $storeId, $filter);

        $countSelect = $connection->select()
            ->from(['sub' => $base], [new \Zend_Db_Expr('COUNT(*)')]);
        $totalCount = (int) $connection->fetchOne($countSelect);

        $totalPages = (int) ceil($totalCount / $pageSize);
        $items = [];
        if ($totalCount > 0 && $currentPage <= max(1, $totalPages)) {
            $listSelect = clone $base;
            $this->applySort($listSelect, $sort);
            $listSelect->limit($pageSize, ($currentPage - 1) * $pageSize);
            $rows = $connection->fetchAll($listSelect);
            $items = $this->enrich($rows);
        }

        return [
            'items' => $items,
            'total_count' => $totalCount,
            'current_page' => $currentPage,
            'page_size' => $pageSize,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Core review rows joined with extra data + rating + media count, filtered.
     *
     * @param int $productId
     * @param int $storeId
     * @param array<string,mixed> $filter
     * @return \Magento\Framework\DB\Select
     */
    private function buildBaseSelect(int $productId, int $storeId, array $filter)
    {
        $connection = $this->resource->getConnection();

        $ratingSub = $connection->select()
            ->from(
                $this->resource->getTableName('rating_option_vote'),
                ['review_id', 'stars' => new \Zend_Db_Expr('ROUND(AVG(percent) / 20, 2)')]
            )
            ->group('review_id');

        $mediaCountSub = $connection->select()
            ->from(
                $this->resource->getTableName('etechflow_review_media'),
                ['review_id', 'media_count' => new \Zend_Db_Expr('COUNT(*)')]
            )
            ->group('review_id');

        $select = $connection->select()
            ->from(
                ['r' => $this->resource->getTableName('review')],
                ['review_id' => 'r.review_id', 'created_at' => 'r.created_at']
            )
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
            ->join(
                ['rd' => $this->resource->getTableName('review_detail')],
                'rd.review_id = r.review_id',
                ['title' => 'rd.title', 'detail' => 'rd.detail', 'nickname' => 'rd.nickname']
            )
            ->joinLeft(
                ['e' => $this->resource->getTableName('etechflow_review_extra')],
                'e.review_id = r.review_id',
                [
                    'pros' => 'e.pros',
                    'cons' => 'e.cons',
                    'is_recommended' => 'e.is_recommended',
                    'is_verified_buyer' => 'e.is_verified_buyer',
                    'helpful_count' => 'e.helpful_count',
                    'not_helpful_count' => 'e.not_helpful_count',
                    'original_language' => 'e.original_language',
                ]
            )
            ->joinLeft(['rating' => $ratingSub], 'rating.review_id = r.review_id', ['rating' => 'rating.stars'])
            ->joinLeft(['mc' => $mediaCountSub], 'mc.review_id = r.review_id', ['media_count' => 'mc.media_count'])
            ->where('re.entity_code = ?', Review::ENTITY_PRODUCT_CODE)
            ->where('r.entity_pk_value = ?', $productId)
            ->where('r.status_id = ?', Review::STATUS_APPROVED)
            ->where('rs.store_id IN (?)', [$storeId, 0])
            ->group('r.review_id');

        if (!empty($filter['rating'])) {
            $select->having('ROUND(rating.stars) = ?', (int) $filter['rating']);
        }
        if (!empty($filter['verified_only'])) {
            $select->where('e.is_verified_buyer = ?', 1);
        }
        if (!empty($filter['with_media'])) {
            $select->having('media_count > 0');
        }

        return $select;
    }

    /**
     * @param \Magento\Framework\DB\Select $select
     * @param string|null $sort
     * @return void
     */
    private function applySort($select, ?string $sort): void
    {
        switch ($sort) {
            case self::SORT_OLDEST:
                $select->order('r.created_at ASC');
                break;
            case self::SORT_HELPFUL:
                $select->order('e.helpful_count DESC')->order('r.created_at DESC');
                break;
            case self::SORT_HIGHEST:
                $select->order('rating.stars DESC')->order('r.created_at DESC');
                break;
            case self::SORT_LOWEST:
                $select->order('rating.stars ASC')->order('r.created_at DESC');
                break;
            case self::SORT_RECENT:
            default:
                $select->order('r.created_at DESC');
                break;
        }
    }

    /**
     * Attach media + comments to the page of review rows (batched).
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function enrich(array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $reviewIds = array_map(static fn ($r) => (int) $r['review_id'], $rows);
        $mediaByReview = $this->loadMedia($reviewIds);
        $commentsByReview = $this->loadComments($reviewIds);

        $items = [];
        foreach ($rows as $row) {
            $reviewId = (int) $row['review_id'];
            $items[] = [
                'review_id' => $reviewId,
                'title' => (string) $row['title'],
                'detail' => (string) $row['detail'],
                'nickname' => (string) $row['nickname'],
                'rating' => (float) ($row['rating'] ?? 0),
                'created_at' => (string) $row['created_at'],
                'pros' => $this->splitLines($row['pros'] ?? null),
                'cons' => $this->splitLines($row['cons'] ?? null),
                'is_recommended' => (bool) ($row['is_recommended'] ?? false),
                'is_verified_buyer' => (bool) ($row['is_verified_buyer'] ?? false),
                'helpful_count' => (int) ($row['helpful_count'] ?? 0),
                'not_helpful_count' => (int) ($row['not_helpful_count'] ?? 0),
                'original_language' => $row['original_language'] ?? null,
                'media' => $mediaByReview[$reviewId] ?? [],
                'comments' => $commentsByReview[$reviewId] ?? [],
            ];
        }
        return $items;
    }

    /**
     * @param int[] $reviewIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function loadMedia(array $reviewIds): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('etechflow_review_media'))
            ->where('review_id IN (?)', $reviewIds)
            ->order('sort_order ASC')
            ->order('media_id ASC');

        $baseUrl = $this->getMediaBaseUrl();
        $out = [];
        foreach ($connection->fetchAll($select) as $row) {
            $reviewId = (int) $row['review_id'];
            $out[$reviewId][] = [
                'media_id' => (int) $row['media_id'],
                'media_type' => (string) $row['media_type'],
                'url' => $row['file_path'] ? $baseUrl . ltrim((string) $row['file_path'], '/') : '',
                'thumbnail_url' => $row['thumbnail_path'] ? $baseUrl . ltrim((string) $row['thumbnail_path'], '/') : '',
            ];
        }
        return $out;
    }

    /**
     * Approved comments grouped by review (oldest first for thread order).
     *
     * @param int[] $reviewIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function loadComments(array $reviewIds): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('etechflow_review_comment'))
            ->where('review_id IN (?)', $reviewIds)
            ->where('status = ?', 2)
            ->where('visible_in_account_only = ?', 0)
            ->order('created_at ASC');

        $out = [];
        foreach ($connection->fetchAll($select) as $row) {
            $reviewId = (int) $row['review_id'];
            $out[$reviewId][] = [
                'comment_id' => (int) $row['comment_id'],
                'author_name' => (string) $row['author_name'],
                'comment' => (string) $row['comment'],
                'is_admin_reply' => (bool) $row['is_admin_reply'],
                'created_at' => (string) $row['created_at'],
            ];
        }
        return $out;
    }

    /**
     * @return string
     */
    private function getMediaBaseUrl(): string
    {
        try {
            return $this->storeManager->getStore()
                ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * @param string|null $blob
     * @return string[]
     */
    private function splitLines(?string $blob): array
    {
        if (!$blob) {
            return [];
        }
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $blob))));
    }
}
