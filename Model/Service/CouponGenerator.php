<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Service;

use Magento\SalesRule\Api\CouponManagementInterface;
use Magento\SalesRule\Api\Data\CouponGenerationSpecInterfaceFactory;
use Psr\Log\LoggerInterface;

/**
 * Generates a single auto-generated coupon code for a configured Cart Price Rule.
 *
 * The referenced rule must have "Coupon = Specific Coupon" + "Use Auto Generation"
 * enabled, otherwise Magento's coupon service refuses to generate. Failures are
 * swallowed (logged) so a missing coupon never blocks the reminder email.
 */
class CouponGenerator
{
    private const CODE_LENGTH = 12;
    private const CODE_FORMAT = 'alphanum';

    /**
     * @param CouponManagementInterface $couponManagement
     * @param CouponGenerationSpecInterfaceFactory $specFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CouponManagementInterface $couponManagement,
        private readonly CouponGenerationSpecInterfaceFactory $specFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Generate one coupon code for the given rule, or null on any failure.
     *
     * @param int $ruleId
     * @return string|null
     */
    public function generateForRule(int $ruleId): ?string
    {
        if ($ruleId <= 0) {
            return null;
        }

        try {
            $spec = $this->specFactory->create([
                'data' => [
                    'rule_id' => $ruleId,
                    'quantity' => 1,
                    'length' => self::CODE_LENGTH,
                    'format' => self::CODE_FORMAT,
                ],
            ]);

            $codes = $this->couponManagement->generate($spec);
            $code = is_array($codes) ? reset($codes) : null;
            return $code !== false && $code !== null ? (string) $code : null;
        } catch (\Exception $e) {
            $this->logger->error('[ETechFlow Reviews] Coupon generation failed for rule '
                . $ruleId . ': ' . $e->getMessage());
            return null;
        }
    }
}
