<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Review reminder resource model.
 */
class Reminder extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('etechflow_review_reminder', 'reminder_id');
    }

    /**
     * Is there already a reminder row for this order? (idempotency guard)
     *
     * @param int $orderId
     * @return bool
     */
    public function existsForOrder(int $orderId): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), 'reminder_id')
            ->where('order_id = ?', $orderId)
            ->limit(1);
        return (bool) $connection->fetchOne($select);
    }

    /**
     * Has the customer already reviewed any of the given products?
     *
     * Used to suppress reminders for shoppers who have already left feedback.
     *
     * @param int $customerId
     * @param int[] $productIds
     * @return bool
     */
    public function customerHasReviewedProducts(int $customerId, array $productIds): bool
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds)));
        if ($customerId <= 0 || empty($productIds)) {
            return false;
        }

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['r' => $this->getTable('review')], 'r.review_id')
            ->join(['rd' => $this->getTable('review_detail')], 'rd.review_id = r.review_id', [])
            ->where('rd.customer_id = ?', $customerId)
            ->where('r.entity_pk_value IN (?)', $productIds)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }
}
