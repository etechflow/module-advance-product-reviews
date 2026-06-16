<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Service;

use ETechFlow\AdvancedProductReviews\Model\Config;
use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Sends the post-purchase review reminder email for one reminder row.
 *
 * Renders the admin-selected (or default) email template in the frontend area
 * for the order's store, suspending inline translation around the send.
 */
class ReminderEmailSender
{
    private const DEFAULT_TEMPLATE_ID = 'etechflow_reviews_reminder_email_template';
    private const EMAIL_IDENTITY = 'general';

    /**
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    /**
     * Send the reminder email.
     *
     * @param string $toEmail
     * @param string $toName
     * @param int $storeId
     * @param array{customer_name:string,review_url:string,coupon_code:?string} $vars
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\MailException
     */
    public function send(string $toEmail, string $toName, int $storeId, array $vars): void
    {
        $store = $this->storeManager->getStore($storeId);
        $templateId = (string) ($this->config->getValue(Config::XML_PATH_REMINDER_EMAIL_TEMPLATE, $storeId)
            ?: self::DEFAULT_TEMPLATE_ID);

        $this->inlineTranslation->suspend();
        try {
            $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars([
                    'customer_name' => $vars['customer_name'] ?? '',
                    'review_url' => $vars['review_url'] ?? '',
                    'coupon_code' => $vars['coupon_code'] ?? '',
                    'store' => $store,
                ])
                ->setFromByScope(self::EMAIL_IDENTITY, $storeId)
                ->addTo($toEmail, $toName !== '' ? $toName : $toEmail);

            $transport = $this->transportBuilder->getTransport();
            $transport->sendMessage();
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
