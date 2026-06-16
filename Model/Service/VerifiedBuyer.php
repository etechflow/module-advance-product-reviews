<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order;

/**
 * Determines whether a customer has actually purchased a given product,
 * used to flag reviews as "Verified Buyer".
 */
class VerifiedBuyer
{
    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Has this customer bought this product in a valid (non-cancelled) order?
     *
     * @param int $customerId
     * @param int $productId
     * @return bool
     */
    public function hasPurchased(int $customerId, int $productId): bool
    {
        if ($customerId <= 0 || $productId <= 0) {
            return false;
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['oi' => $this->resource->getTableName('sales_order_item')], 'oi.item_id')
            ->join(
                ['o' => $this->resource->getTableName('sales_order')],
                'oi.order_id = o.entity_id',
                []
            )
            ->where('o.customer_id = ?', $customerId)
            ->where('oi.product_id = ?', $productId)
            ->where('o.state NOT IN (?)', [Order::STATE_CANCELED])
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    /**
     * Has this email bought this product (covers guest checkouts)?
     *
     * @param string $email
     * @param int $productId
     * @return bool
     */
    public function hasPurchasedByEmail(string $email, int $productId): bool
    {
        if ($email === '' || $productId <= 0) {
            return false;
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['oi' => $this->resource->getTableName('sales_order_item')], 'oi.item_id')
            ->join(
                ['o' => $this->resource->getTableName('sales_order')],
                'oi.order_id = o.entity_id',
                []
            )
            ->where('o.customer_email = ?', $email)
            ->where('oi.product_id = ?', $productId)
            ->where('o.state NOT IN (?)', [Order::STATE_CANCELED])
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }
}
