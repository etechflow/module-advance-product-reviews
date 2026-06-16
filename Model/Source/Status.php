<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Moderation status options shared by comments and Q&A.
 */
class Status implements OptionSourceInterface
{
    public const PENDING = 1;
    public const APPROVED = 2;
    public const REJECTED = 3;

    /**
     * @return array<int, array{value:int,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::PENDING, 'label' => __('Pending')],
            ['value' => self::APPROVED, 'label' => __('Approved')],
            ['value' => self::REJECTED, 'label' => __('Rejected')],
        ];
    }

    /**
     * @return array<int, \Magento\Framework\Phrase>
     */
    public function toArray(): array
    {
        return [
            self::PENDING => __('Pending'),
            self::APPROVED => __('Approved'),
            self::REJECTED => __('Rejected'),
        ];
    }
}
