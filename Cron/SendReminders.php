<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Cron;

use ETechFlow\AdvancedProductReviews\Model\Config;
use ETechFlow\AdvancedProductReviews\Model\Reminder;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\Reminder as ReminderResource;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\Reminder\CollectionFactory as ReminderCollectionFactory;
use ETechFlow\AdvancedProductReviews\Model\Service\CouponGenerator;
use ETechFlow\AdvancedProductReviews\Model\Service\ReminderEmailSender;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Cron: send due post-purchase review reminders.
 *
 * Picks up pending reminders whose scheduled time has passed, optionally
 * generates a thank-you coupon, sends the email, and marks the row sent.
 * Each reminder is processed independently so one failure can't stall the rest.
 */
class SendReminders
{
    private const BATCH_SIZE = 100;

    /**
     * @param Config $config
     * @param ReminderCollectionFactory $collectionFactory
     * @param ReminderResource $reminderResource
     * @param OrderRepositoryInterface $orderRepository
     * @param ProductRepositoryInterface $productRepository
     * @param CouponGenerator $couponGenerator
     * @param ReminderEmailSender $emailSender
     * @param StoreManagerInterface $storeManager
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly ReminderCollectionFactory $collectionFactory,
        private readonly ReminderResource $reminderResource,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CouponGenerator $couponGenerator,
        private readonly ReminderEmailSender $emailSender,
        private readonly StoreManagerInterface $storeManager,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $now = $this->dateTime->gmtDate();
        $collection = $this->collectionFactory->create()->addDueFilter($now, self::BATCH_SIZE);
        if ($collection->getSize() === 0) {
            return;
        }

        foreach ($collection as $reminder) {
            try {
                $this->processReminder($reminder, $now);
            } catch (\Exception $e) {
                // Leave the row pending so the next run retries it.
                $this->logger->error('[ETechFlow Reviews] Reminder ' . $reminder->getId()
                    . ' failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Process one due reminder: guard, coupon, email, mark sent.
     *
     * @param Reminder $reminder
     * @param string $now MySQL datetime (GMT)
     * @return void
     */
    private function processReminder(Reminder $reminder, string $now): void
    {
        $storeId = (int) $reminder->getStoreId();

        // Respect a feature toggle that was switched off after scheduling.
        if (!$this->config->isFlag(Config::XML_PATH_REMINDER_ENABLED, $storeId)) {
            return;
        }

        $order = $this->orderRepository->get((int) $reminder->getOrderId());
        $productIds = $this->collectProductIds($order);

        // Don't nag customers who already reviewed one of these products.
        $customerId = $reminder->getCustomerId() ? (int) $reminder->getCustomerId() : 0;
        if ($customerId > 0 && $this->reminderResource->customerHasReviewedProducts($customerId, $productIds)) {
            $reminder->setStatus(Reminder::STATUS_CANCELLED)->setSentAt($now);
            $this->reminderResource->save($reminder);
            return;
        }

        // Optional thank-you coupon.
        $couponCode = null;
        if ($this->config->isFlag(Config::XML_PATH_REMINDER_COUPON_ENABLED, $storeId)) {
            $ruleId = (int) $this->config->getValue(Config::XML_PATH_REMINDER_COUPON_RULE, $storeId);
            $couponCode = $this->couponGenerator->generateForRule($ruleId);
        }

        $this->emailSender->send(
            (string) $reminder->getCustomerEmail(),
            (string) $order->getCustomerName(),
            $storeId,
            [
                'customer_name' => $this->resolveFirstName($order),
                'review_url' => $this->buildReviewUrl($productIds, $storeId),
                'coupon_code' => $couponCode,
            ]
        );

        $reminder->setStatus(Reminder::STATUS_SENT)
            ->setSentAt($now)
            ->setCouponCode($couponCode);
        $this->reminderResource->save($reminder);
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return int[]
     */
    private function collectProductIds($order): array
    {
        $ids = [];
        foreach ($order->getItems() as $item) {
            $productId = (int) $item->getProductId();
            if ($productId > 0) {
                $ids[$productId] = $productId;
            }
        }
        return array_values($ids);
    }

    /**
     * A friendly first name for the greeting, falling back gracefully.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return string
     */
    private function resolveFirstName($order): string
    {
        $firstName = (string) $order->getCustomerFirstname();
        if ($firstName !== '') {
            return $firstName;
        }
        return (string) (__('there'));
    }

    /**
     * Build a "write your review" link pointing at the first purchased product.
     *
     * @param int[] $productIds
     * @param int $storeId
     * @return string
     */
    private function buildReviewUrl(array $productIds, int $storeId): string
    {
        $firstProductId = $productIds[0] ?? 0;
        if ($firstProductId > 0) {
            try {
                $product = $this->productRepository->getById($firstProductId, false, $storeId);
                $url = (string) $product->getProductUrl();
                if ($url !== '') {
                    return $url;
                }
            } catch (\Exception $e) {
                $this->logger->debug('[ETechFlow Reviews] Could not resolve product URL: ' . $e->getMessage());
            }
        }

        try {
            return $this->storeManager->getStore($storeId)->getBaseUrl();
        } catch (\Exception $e) {
            return '';
        }
    }
}
