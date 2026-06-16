<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\ResourceModel\Reminder;

use ETechFlow\AdvancedProductReviews\Model\Reminder as ReminderModel;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\Reminder as ReminderResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Review reminder collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'reminder_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ReminderModel::class, ReminderResource::class);
    }

    /**
     * Pending reminders whose scheduled time has arrived.
     *
     * @param string $now MySQL datetime string
     * @param int $limit
     * @return $this
     */
    public function addDueFilter(string $now, int $limit = 100): self
    {
        $this->addFieldToFilter('status', ReminderModel::STATUS_PENDING);
        $this->addFieldToFilter('scheduled_at', ['lteq' => $now]);
        $this->setOrder('scheduled_at', self::SORT_ORDER_ASC);
        $this->setPageSize($limit);
        return $this;
    }
}
