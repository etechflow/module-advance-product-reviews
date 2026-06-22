<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Service\Translator;

/**
 * Contract for an AI translation provider client.
 */
interface TranslatorInterface
{
    /**
     * Translate a map of named text fields into the target language.
     *
     * @param array<string,string> $fields Non-empty source fields keyed by name (title/detail/pros/cons)
     * @param string $targetLanguage Human-readable language name (e.g. "German")
     * @param string $encryptedApiKey API key as stored (encrypted) in config
     * @param string $model Provider model id
     * @return array<string,string> Translated fields keyed identically to the input
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function translate(array $fields, string $targetLanguage, string $encryptedApiKey, string $model): array;
}
