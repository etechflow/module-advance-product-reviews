<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Resolver;

use ETechFlow\AdvancedProductReviews\Model\Config;
use ETechFlow\AdvancedProductReviews\Model\Service\TranslationService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Mutation `etfTranslateReview` — translate a review into the requested
 * language (Claude-powered, DB-cached). Returns split pros/cons arrays so the
 * shape matches `EtfReview`.
 */
class TranslateReview implements ResolverInterface
{
    /**
     * @param TranslationService $translationService
     * @param Config $config
     */
    public function __construct(
        private readonly TranslationService $translationService,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        if (!$this->config->isEnabled()) {
            return ['success' => false, 'message' => (string) __('Reviews are not available.')];
        }
        $input = $args['input'] ?? [];
        $reviewId = (int) ($input['review_id'] ?? 0);
        $language = trim((string) ($input['language'] ?? ''));
        if ($reviewId <= 0 || $language === '') {
            throw new GraphQlInputException(__('Please provide "review_id" and "language".'));
        }

        $store = $context->getExtensionAttributes()->getStore();
        $storeId = $store instanceof StoreInterface ? (int) $store->getId() : null;

        try {
            $result = $this->translationService->translateReview($reviewId, $storeId, $language);
            return [
                'success' => true,
                'message' => (string) __('Translation ready.'),
                'language' => $result['language'],
                'title' => $result['title'],
                'detail' => $result['detail'],
                'pros' => $this->splitLines($result['pros'] ?? null),
                'cons' => $this->splitLines($result['cons'] ?? null),
            ];
        } catch (LocalizedException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => (string) __('Could not translate this review.')];
        }
    }

    /**
     * @param string|null $blob
     * @return string[]
     */
    private function splitLines(?string $blob): array
    {
        if (!$blob) {
            return [];
        }
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $blob))));
    }
}
