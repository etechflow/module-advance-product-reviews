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
 * Review vote resource model.
 */
class ReviewVote extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('etechflow_review_vote', 'vote_id');
    }

    /**
     * Has this customer already voted on this review?
     *
     * @param int $reviewId
     * @param int $customerId
     * @return bool
     */
    public function hasCustomerVoted(int $reviewId, int $customerId): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), 'vote_id')
            ->where('review_id = ?', $reviewId)
            ->where('customer_id = ?', $customerId)
            ->limit(1);
        return (bool) $connection->fetchOne($select);
    }

    /**
     * Has this guest (by visitor hash) already voted on this review?
     *
     * @param int $reviewId
     * @param string $visitorHash
     * @return bool
     */
    public function hasGuestVoted(int $reviewId, string $visitorHash): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), 'vote_id')
            ->where('review_id = ?', $reviewId)
            ->where('visitor_hash = ?', $visitorHash)
            ->limit(1);
        return (bool) $connection->fetchOne($select);
    }
}
