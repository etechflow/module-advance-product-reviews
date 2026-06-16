<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Central configuration reader for the extension.
 *
 * All settings live under the `etechflow_reviews/*` config path and are
 * resolved at store-view scope so behaviour can differ per store/language.
 */
class Config
{
    /** General */
    public const XML_PATH_ENABLED = 'etechflow_reviews/general/enabled';
    public const XML_PATH_GUEST_REVIEWS = 'etechflow_reviews/general/allow_guest_reviews';
    public const XML_PATH_AUTO_APPROVE = 'etechflow_reviews/general/auto_approve';

    /** Review elements */
    public const XML_PATH_ENABLE_PROS_CONS = 'etechflow_reviews/elements/enable_pros_cons';
    public const XML_PATH_ENABLE_RECOMMEND = 'etechflow_reviews/elements/enable_recommend';
    public const XML_PATH_ENABLE_HELPFUL = 'etechflow_reviews/elements/enable_helpful';
    public const XML_PATH_ENABLE_COMMENTS = 'etechflow_reviews/elements/enable_comments';
    public const XML_PATH_GUEST_COMMENTS = 'etechflow_reviews/elements/allow_guest_comments';
    public const XML_PATH_ENABLE_QA = 'etechflow_reviews/elements/enable_qa';

    /** Media */
    public const XML_PATH_ENABLE_IMAGES = 'etechflow_reviews/media/enable_images';
    public const XML_PATH_ENABLE_VIDEOS = 'etechflow_reviews/media/enable_videos';
    public const XML_PATH_MAX_IMAGES = 'etechflow_reviews/media/max_images';
    public const XML_PATH_MAX_IMAGE_SIZE = 'etechflow_reviews/media/max_image_size_mb';
    public const XML_PATH_MAX_VIDEO_SIZE = 'etechflow_reviews/media/max_video_size_mb';
    public const XML_PATH_ALLOWED_VIDEO_TYPES = 'etechflow_reviews/media/allowed_video_types';

    /** Translation (Claude) */
    public const XML_PATH_TRANSLATION_ENABLED = 'etechflow_reviews/translation/enabled';
    public const XML_PATH_TRANSLATION_API_KEY = 'etechflow_reviews/translation/claude_api_key';
    public const XML_PATH_TRANSLATION_MODEL = 'etechflow_reviews/translation/claude_model';
    public const XML_PATH_TRANSLATION_AUTO = 'etechflow_reviews/translation/auto_translate';

    /** Spam / CAPTCHA */
    public const XML_PATH_CAPTCHA_ENABLED = 'etechflow_reviews/spam/captcha_enabled';

    /** Reminders */
    public const XML_PATH_REMINDER_ENABLED = 'etechflow_reviews/reminder/enabled';
    public const XML_PATH_REMINDER_DELAY_DAYS = 'etechflow_reviews/reminder/delay_days';
    public const XML_PATH_REMINDER_COUPON_ENABLED = 'etechflow_reviews/reminder/coupon_enabled';
    public const XML_PATH_REMINDER_COUPON_RULE = 'etechflow_reviews/reminder/coupon_rule_id';
    public const XML_PATH_REMINDER_EMAIL_TEMPLATE = 'etechflow_reviews/reminder/email_template';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LicenseValidator $licenseValidator
    ) {
    }

    /**
     * Generic flag reader at store scope.
     *
     * @param string $path
     * @param int|string|null $storeId
     * @return bool
     */
    public function isFlag(string $path, $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Generic value reader at store scope.
     *
     * @param string $path
     * @param int|string|null $storeId
     * @return mixed
     */
    public function getValue(string $path, $storeId = null)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Master enable. Returns FALSE if the license is invalid (gating every
     * entry point per LICENSING_PROTOCOL.md) OR the admin toggle is Off.
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null): bool
    {
        if (!$this->licenseValidator->isValid()) {
            return false;
        }
        return $this->isFlag(self::XML_PATH_ENABLED, $storeId);
    }

    /**
     * @param int|string|null $storeId
     * @return bool
     */
    public function isTranslationEnabled($storeId = null): bool
    {
        return $this->isFlag(self::XML_PATH_TRANSLATION_ENABLED, $storeId);
    }

    /**
     * @param int|string|null $storeId
     * @return string
     */
    public function getClaudeApiKey($storeId = null): string
    {
        return (string) $this->getValue(self::XML_PATH_TRANSLATION_API_KEY, $storeId);
    }

    /**
     * @param int|string|null $storeId
     * @return string
     */
    public function getClaudeModel($storeId = null): string
    {
        return (string) ($this->getValue(self::XML_PATH_TRANSLATION_MODEL, $storeId) ?: 'claude-haiku-4-5-20251001');
    }

    /**
     * @param int|string|null $storeId
     * @return bool
     */
    public function isVideoEnabled($storeId = null): bool
    {
        return $this->isFlag(self::XML_PATH_ENABLE_VIDEOS, $storeId);
    }

    /**
     * @param int|string|null $storeId
     * @return int
     */
    public function getMaxVideoSizeMb($storeId = null): int
    {
        return (int) ($this->getValue(self::XML_PATH_MAX_VIDEO_SIZE, $storeId) ?: 50);
    }

    /**
     * Allowed video file extensions as a lowercase array.
     *
     * @param int|string|null $storeId
     * @return string[]
     */
    public function getAllowedVideoTypes($storeId = null): array
    {
        $raw = (string) $this->getValue(self::XML_PATH_ALLOWED_VIDEO_TYPES, $storeId);
        if ($raw === '') {
            return ['mp4', 'webm', 'mov'];
        }
        return array_values(array_filter(array_map(
            static fn ($t) => strtolower(trim($t)),
            explode(',', $raw)
        )));
    }

    /**
     * @param int|string|null $storeId
     * @return int
     */
    public function getReminderDelayDays($storeId = null): int
    {
        return (int) ($this->getValue(self::XML_PATH_REMINDER_DELAY_DAYS, $storeId) ?: 7);
    }
}
