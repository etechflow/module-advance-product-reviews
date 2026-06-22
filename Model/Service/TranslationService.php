<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Service;

use ETechFlow\AdvancedProductReviews\Api\ReviewExtraRepositoryInterface;
use ETechFlow\AdvancedProductReviews\Model\Config;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewTranslation as ReviewTranslationResource;
use ETechFlow\AdvancedProductReviews\Model\ReviewTranslationFactory;
use ETechFlow\AdvancedProductReviews\Model\Service\Translator\TranslatorPool;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\ResolverInterface as LocaleResolver;
use Magento\Review\Model\ReviewFactory;
use Psr\Log\LoggerInterface;

/**
 * Translates reviews into the storefront language via the configured AI
 * provider (OpenAI / Gemini / Anthropic), with DB caching.
 *
 * Flow: resolve target language -> return DB-cached translation if present ->
 * otherwise call the active provider, persist the result, and return it. The
 * cache table has a unique (review_id, language) key so each pair is translated
 * once.
 */
class TranslationService
{
    /**
     * @param Config $config
     * @param TranslatorPool $translatorPool
     * @param ReviewTranslationResource $translationResource
     * @param ReviewTranslationFactory $translationFactory
     * @param ReviewExtraRepositoryInterface $extraRepository
     * @param ReviewFactory $reviewFactory
     * @param LocaleResolver $localeResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly TranslatorPool $translatorPool,
        private readonly ReviewTranslationResource $translationResource,
        private readonly ReviewTranslationFactory $translationFactory,
        private readonly ReviewExtraRepositoryInterface $extraRepository,
        private readonly ReviewFactory $reviewFactory,
        private readonly LocaleResolver $localeResolver,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Translate one review into the current storefront language.
     *
     * @param int $reviewId
     * @param int|string|null $storeId
     * @param string|null $language Explicit target language code (headless clients);
     *                              falls back to the storefront locale when null.
     * @return array{language:string,title:?string,detail:?string,pros:?string,cons:?string,cached:bool}
     * @throws LocalizedException
     */
    public function translateReview(int $reviewId, $storeId = null, ?string $language = null): array
    {
        if (!$this->config->isTranslationEnabled($storeId)) {
            throw new LocalizedException(__('Translation is not enabled.'));
        }
        if ($reviewId <= 0) {
            throw new LocalizedException(__('Invalid review.'));
        }

        $language = $language !== null && trim($language) !== ''
            ? strtolower(substr(trim($language), 0, 2))
            : $this->resolveLanguageCode($storeId);

        // 1) Serve from cache when available.
        $cached = $this->translationResource->getTranslationData($reviewId, $language);
        if ($cached !== null) {
            return $this->shape($language, $cached, true);
        }

        // 2) Gather the source fields from the core review + extra row.
        $source = $this->collectSourceFields($reviewId);
        if (empty($source)) {
            throw new LocalizedException(__('There is nothing to translate.'));
        }

        // 3) Skip the API when the source language already matches the target.
        $extra = $this->extraRepository->getByReviewId($reviewId);
        if ($extra->getOriginalLanguage() && strtolower($extra->getOriginalLanguage()) === $language) {
            throw new LocalizedException(__('This review is already in your language.'));
        }

        // 4) Translate via the configured provider and persist.
        $provider = $this->config->getTranslationProvider($storeId);
        $translated = $this->translatorPool->get($provider)->translate(
            $source,
            $this->languageDisplayName($language),
            $this->config->getTranslationApiKey($storeId),
            $this->config->getTranslationModel($storeId)
        );
        if (empty($translated)) {
            throw new LocalizedException(__('No translation was produced.'));
        }

        $row = $this->persist($reviewId, $language, $translated, $provider);
        return $this->shape($language, $row, false);
    }

    /**
     * Collect translatable fields (title, detail, pros, cons) for a review.
     *
     * @param int $reviewId
     * @return array<string,string>
     */
    private function collectSourceFields(int $reviewId): array
    {
        $review = $this->reviewFactory->create()->load($reviewId);
        $fields = [
            'title' => (string) $review->getTitle(),
            'detail' => (string) $review->getDetail(),
        ];

        try {
            $extra = $this->extraRepository->getByReviewId($reviewId);
            $fields['pros'] = (string) $extra->getPros();
            $fields['cons'] = (string) $extra->getCons();
        } catch (\Exception $e) {
            // No extra row; title/detail are enough.
            $this->logger->debug('[ETechFlow Reviews] No extra row for review ' . $reviewId);
        }

        return array_filter($fields, static fn ($v) => trim($v) !== '');
    }

    /**
     * Persist a fresh translation, tolerating a concurrent insert.
     *
     * @param int $reviewId
     * @param string $language
     * @param array<string,string> $translated
     * @param string $engine Provider code that produced the translation
     * @return array<string,mixed>
     */
    private function persist(int $reviewId, string $language, array $translated, string $engine): array
    {
        $model = $this->translationFactory->create();
        $model->setReviewId($reviewId)
            ->setLanguage($language)
            ->setTranslatedTitle($translated['title'] ?? null)
            ->setTranslatedDetail($translated['detail'] ?? null)
            ->setTranslatedPros($translated['pros'] ?? null)
            ->setTranslatedCons($translated['cons'] ?? null)
            ->setEngine($engine);

        try {
            $this->translationResource->save($model);
        } catch (\Exception $e) {
            // Likely a duplicate from a concurrent request — fall back to the stored row.
            $existing = $this->translationResource->getTranslationData($reviewId, $language);
            if ($existing !== null) {
                return $existing;
            }
            $this->logger->error('[ETechFlow Reviews] Failed to store translation: ' . $e->getMessage());
        }

        return $model->getData();
    }

    /**
     * Normalise a stored/saved row into the response shape.
     *
     * @param string $language
     * @param array<string,mixed> $row
     * @param bool $cached
     * @return array{language:string,title:?string,detail:?string,pros:?string,cons:?string,cached:bool}
     */
    private function shape(string $language, array $row, bool $cached): array
    {
        return [
            'language' => $language,
            'title' => $row['translated_title'] ?? null,
            'detail' => $row['translated_detail'] ?? null,
            'pros' => $row['translated_pros'] ?? null,
            'cons' => $row['translated_cons'] ?? null,
            'cached' => $cached,
        ];
    }

    /**
     * Resolve the two-letter language code for the storefront locale.
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function resolveLanguageCode($storeId = null): string
    {
        $locale = (string) $this->localeResolver->getLocale(); // e.g. "de_DE"
        $code = strtolower(substr($locale, 0, 2));
        return $code !== '' ? $code : 'en';
    }

    /**
     * Human-readable English name for a language code, for the prompt.
     *
     * @param string $code
     * @return string
     */
    private function languageDisplayName(string $code): string
    {
        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayLanguage($code, 'en');
            if (is_string($name) && $name !== '' && strtolower($name) !== $code) {
                return $name;
            }
        }
        return $code;
    }
}
