<?php
declare(strict_types=1);
namespace ETechFlow\AdvancedProductReviews\Observer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

class UpdatePendingReviewsNotification implements ObserverInterface
{
    private const TITLE_KEY = 'ETechFlow: pending product reviews';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {}

    public function execute(Observer $observer): void
    {
        try {
            $conn  = $this->resource->getConnection();
            $review = $conn->getTableName('review');
            $inbox  = $conn->getTableName('adminnotification_inbox');
            $count  = (int) $conn->fetchOne("SELECT COUNT(*) FROM `{$review}` WHERE status_id = 1");

            // Remove any stale entry for this notification type
            $conn->delete($inbox, ["title = ?" => self::TITLE_KEY]);

            if ($count > 0) {
                $conn->insert($inbox, [
                    'severity'    => 4,
                    'date_added'  => date('Y-m-d H:i:s'),
                    'title'       => self::TITLE_KEY,
                    'description' => "{$count} product review(s) are awaiting approval.",
                    'url'         => 'review/product/index',
                    'is_read'     => 0,
                    'is_remove'   => 0,
                ]);
            }
        } catch (\Throwable $e) {
            // non-fatal
        }
    }
}
