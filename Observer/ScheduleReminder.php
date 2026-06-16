<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Observer;

use ETechFlow\AdvancedProductReviews\Model\Config;
use ETechFlow\AdvancedProductReviews\Model\Reminder;
use ETechFlow\AdvancedProductReviews\Model\ReminderFactory;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\Reminder as ReminderResource;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Schedules a post-purchase review reminder when an order reaches "complete".
 *
 * Listens on sales_order_save_after; the per-order idempotency guard means
 * the repeated saves a complete order receives only ever create one row.
 */
class ScheduleReminder implements ObserverInterface
{
    /**
     * @param Config $config
     * @param ReminderFactory $reminderFactory
     * @param ReminderResource $reminderResource
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly ReminderFactory $reminderFactory,
        private readonly ReminderResource $reminderResource,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var Order|null $order */
        $order = $observer->getEvent()->getData('order');
        if (!$order instanceof Order || !$order->getId()) {
            return;
        }

        // Only act the moment the order becomes complete.
        if ($order->getState() !== Order::STATE_COMPLETE) {
            return;
        }

        $storeId = (int) $order->getStoreId();
        if (!$this->config->isFlag(Config::XML_PATH_REMINDER_ENABLED, $storeId)) {
            return;
        }

        $orderId = (int) $order->getId();
        $email = (string) $order->getCustomerEmail();
        if ($email === '' || $this->reminderResource->existsForOrder($orderId)) {
            return;
        }

        try {
            $delayDays = $this->config->getReminderDelayDays($storeId);
            $scheduledAt = gmdate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp() + ($delayDays * 86400));

            /** @var Reminder $reminder */
            $reminder = $this->reminderFactory->create();
            $reminder->setOrderId($orderId)
                ->setCustomerId($order->getCustomerId() ? (int) $order->getCustomerId() : null)
                ->setCustomerEmail($email)
                ->setStoreId($storeId)
                ->setStatus(Reminder::STATUS_PENDING)
                ->setReminderStep(1)
                ->setScheduledAt($scheduledAt);
            $this->reminderResource->save($reminder);
        } catch (\Exception $e) {
            // Never let reminder scheduling break order processing.
            $this->logger->error('[ETechFlow Reviews] Failed to schedule reminder for order '
                . $orderId . ': ' . $e->getMessage());
        }
    }
}
