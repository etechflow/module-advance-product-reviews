<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Service\Translator;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Thin client for the Anthropic (Claude) Messages API.
 *
 * Sends a single review's text fields and asks Claude to return a strict JSON
 * object of translated fields. The API key is decrypted here, never logged.
 *
 * @see https://docs.anthropic.com/en/api/messages
 */
class ClaudeClient
{
    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const MAX_TOKENS = 1500;
    private const TIMEOUT_SECONDS = 30;

    /**
     * @param Curl $curl
     * @param Json $json
     * @param EncryptorInterface $encryptor
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly EncryptorInterface $encryptor,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Translate a map of named text fields into the target language.
     *
     * @param array<string,string> $fields Non-empty source fields keyed by name (title/detail/pros/cons)
     * @param string $targetLanguage Human-readable language name (e.g. "German")
     * @param string $encryptedApiKey API key as stored (encrypted) in config
     * @param string $model Claude model id
     * @return array<string,string> Translated fields keyed identically to the input
     * @throws LocalizedException
     */
    public function translate(array $fields, string $targetLanguage, string $encryptedApiKey, string $model): array
    {
        $fields = array_filter($fields, static fn ($v) => is_string($v) && trim($v) !== '');
        if (empty($fields)) {
            return [];
        }

        $apiKey = $this->resolveApiKey($encryptedApiKey);
        if ($apiKey === '') {
            throw new LocalizedException(__('Claude API key is not configured.'));
        }

        $payload = [
            'model' => $model,
            'max_tokens' => self::MAX_TOKENS,
            'system' => $this->buildSystemPrompt($targetLanguage),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $this->json->serialize($fields),
                ],
            ],
        ];

        $responseBody = $this->send($payload, $apiKey);
        return $this->parseTranslation($responseBody, array_keys($fields));
    }

    /**
     * Decrypt the stored key, tolerating an already-plaintext value.
     *
     * @param string $encryptedApiKey
     * @return string
     */
    private function resolveApiKey(string $encryptedApiKey): string
    {
        $encryptedApiKey = trim($encryptedApiKey);
        if ($encryptedApiKey === '') {
            return '';
        }
        // Anthropic keys begin with "sk-"; if already decrypted, use as-is.
        if (str_starts_with($encryptedApiKey, 'sk-')) {
            return $encryptedApiKey;
        }
        try {
            return (string) $this->encryptor->decrypt($encryptedApiKey);
        } catch (\Exception $e) {
            return $encryptedApiKey;
        }
    }

    /**
     * @param string $targetLanguage
     * @return string
     */
    private function buildSystemPrompt(string $targetLanguage): string
    {
        return 'You are a professional e-commerce translator. You will receive a JSON object whose values are '
            . 'parts of a customer product review. Translate every value into ' . $targetLanguage . '. '
            . 'Preserve line breaks within values. Do not translate brand names, model numbers, or URLs. '
            . 'Keep the tone natural and conversational. Respond with ONLY a JSON object using the exact same '
            . 'keys as the input and the translated strings as values. No markdown, no commentary.';
    }

    /**
     * Execute the HTTP request and return the decoded response body.
     *
     * @param array $payload
     * @param string $apiKey
     * @return array
     * @throws LocalizedException
     */
    private function send(array $payload, string $apiKey): array
    {
        $this->curl->setTimeout(self::TIMEOUT_SECONDS);
        $this->curl->addHeader('content-type', 'application/json');
        $this->curl->addHeader('x-api-key', $apiKey);
        $this->curl->addHeader('anthropic-version', self::API_VERSION);

        try {
            $this->curl->post(self::API_ENDPOINT, $this->json->serialize($payload));
        } catch (\Exception $e) {
            $this->logger->error('[ETechFlow Reviews] Claude request failed: ' . $e->getMessage());
            throw new LocalizedException(__('Could not reach the translation service.'));
        }

        $status = $this->curl->getStatus();
        $body = $this->curl->getBody();

        if ($status !== 200) {
            $this->logger->error('[ETechFlow Reviews] Claude API HTTP ' . $status . ': ' . $body);
            throw new LocalizedException(__('The translation service returned an error (%1).', $status));
        }

        try {
            $decoded = $this->json->unserialize($body);
        } catch (\Exception $e) {
            throw new LocalizedException(__('The translation service returned an invalid response.'));
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Extract the translated JSON object from the Messages API response.
     *
     * @param array $response
     * @param string[] $expectedKeys
     * @return array<string,string>
     * @throws LocalizedException
     */
    private function parseTranslation(array $response, array $expectedKeys): array
    {
        $text = '';
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }
        $text = trim($text);
        if ($text === '') {
            throw new LocalizedException(__('The translation service returned no text.'));
        }

        // Strip a ```json fence if the model added one despite instructions.
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text);

        try {
            $decoded = $this->json->unserialize(trim($text));
        } catch (\Exception $e) {
            $this->logger->error('[ETechFlow Reviews] Could not parse Claude translation JSON: ' . $text);
            throw new LocalizedException(__('The translation could not be understood.'));
        }

        $result = [];
        foreach ($expectedKeys as $key) {
            if (isset($decoded[$key]) && is_string($decoded[$key])) {
                $result[$key] = $decoded[$key];
            }
        }
        return $result;
    }
}
