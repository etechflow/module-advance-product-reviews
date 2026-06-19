<?php
declare(strict_types=1);
/**
 * ETechFlow_AdvancedProductReviews — admin notification: pending reviews
 */
namespace ETechFlow\AdvancedProductReviews\Model\System\Message;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Notification\MessageInterface;
use Magento\Backend\Model\UrlInterface;

class PendingReviews implements MessageInterface
{
    private ResourceConnection $resource;
    private UrlInterface $backendUrl;
    private ?int $count = null;

    public function __construct(ResourceConnection $resource, UrlInterface $backendUrl)
    {
        $this->resource   = $resource;
        $this->backendUrl = $backendUrl;
    }

    public function getIdentity(): string
    {
        return 'etechflow_apr_pending_reviews';
    }

    public function isDisplayed(): bool
    {
        return $this->getPendingCount() > 0;
    }

    public function getText(): \Magento\Framework\Phrase
    {
        $count = $this->getPendingCount();
        $url   = $this->backendUrl->getUrl('review/product/index');
        return __(
            '%1 product review(s) are awaiting approval. <a href="%2">Review now &rarr;</a>',
            $count,
            $url
        );
    }

    public function getSeverity(): int
    {
        return self::SEVERITY_NOTICE;
    }

    private function getPendingCount(): int
    {
        if ($this->count === null) {
            try {
                $conn        = $this->resource->getConnection();
                $table       = $conn->getTableName('review');
                // status_id 1 = Pending (Magento\Review\Model\Review::STATUS_PENDING)
                $this->count = (int) $conn->fetchOne(
                    "SELECT COUNT(*) FROM `{$table}` WHERE status_id = 1"
                );
            } catch (\Exception $e) {
                $this->count = 0;
            }
        }
        return $this->count;
    }
}
