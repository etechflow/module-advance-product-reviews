<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model;

use ETechFlow\AdvancedProductReviews\Model\ResourceModel\Reminder as ReminderResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Scheduled post-purchase review reminder.
 *
 * @method int getOrderId()
 * @method $this setOrderId(int $orderId)
 * @method int|null getCustomerId()
 * @method $this setCustomerId(?int $customerId)
 * @method string getCustomerEmail()
 * @method $this setCustomerEmail(string $email)
 * @method int getStoreId()
 * @method $this setStoreId(int $storeId)
 * @method string getStatus()
 * @method $this setStatus(string $status)
 * @method int getReminderStep()
 * @method $this setReminderStep(int $step)
 * @method string|null getScheduledAt()
 * @method $this setScheduledAt(?string $scheduledAt)
 * @method string|null getSentAt()
 * @method $this setSentAt(?string $sentAt)
 * @method string|null getCouponCode()
 * @method $this setCouponCode(?string $code)
 */
class Reminder extends AbstractModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var string
     */
    protected $_eventPrefix = 'etechflow_review_reminder';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ReminderResource::class);
    }
}
