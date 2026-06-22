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
 * Shared behaviour for AI translation clients: key decryption, the translation
 * system prompt, the HTTP call, and strict JSON-object parsing. Concrete clients
 * only implement the provider-specific request/response shape in callModel().
 *
 * Technical failures (HTTP errors, quota/billing, unreachable, bad response) are
 * logged with full detail for the admin but surfaced to shoppers as a single,
 * friendly "try again later" message — never a raw status code.
 */
abstract class AbstractTranslator implements TranslatorInterface
{
    protected const MAX_TOKENS = 1500;
    protected const TIMEOUT_SECONDS = 30;

    /**
     * @param Curl $curl
     * @param Json $json
     * @param EncryptorInterface $encryptor
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected readonly Curl $curl,
        protected readonly Json $json,
        protected readonly EncryptorInterface $encryptor,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function translate(array $fields, string $targetLanguage, string $encryptedApiKey, string $model): array
    {
        $fields = array_filter($fields, static fn ($v) => is_string($v) && trim($v) !== '');
        if (empty($fields)) {
            return [];
        }

        $apiKey = $this->resolveApiKey($encryptedApiKey);
        if ($apiKey === '') {
            $this->logger->error('[ETechFlow Reviews] ' . $this->getProviderLabel()
                . ' API key is not configured.');
            throw $this->unavailable();
        }

        $text = $this->callModel($fields, $targetLanguage, $apiKey, $model);
        return $this->extractTranslatedFields($text, array_keys($fields));
    }

    /**
     * Provider-specific call: send the fields and return the model's raw text
     * output (expected to be a JSON object string).
     *
     * @param array<string,string> $fields
     * @param string $targetLanguage
     * @param string $apiKey Decrypted key
     * @param string $model
     * @return string
     * @throws LocalizedException
     */
    abstract protected function callModel(array $fields, string $targetLanguage, string $apiKey, string $model): string;

    /**
     * Human-readable provider name, used in logs.
     *
     * @return string
     */
    abstract protected function getProviderLabel(): string;

    /**
     * The shopper-facing message for any technical translation failure.
     *
     * @return LocalizedException
     */
    protected function unavailable(): LocalizedException
    {
        return new LocalizedException(
            __('Sorry, this review could not be translated right now. Please try again later.')
        );
    }

    /**
     * Decrypt the stored key, tolerating an already-plaintext value.
     *
     * @param string $stored
     * @return string
     */
    protected function resolveApiKey(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }
        try {
            $decrypted = (string) $this->encryptor->decrypt($stored);
        } catch (\Exception $e) {
            $decrypted = '';
        }
        return $decrypted !== '' ? $decrypted : $stored;
    }

    /**
     * The shared translation instruction.
     *
     * @param string $targetLanguage
     * @return string
     */
    protected function buildSystemPrompt(string $targetLanguage): string
    {
        return 'You are a professional e-commerce translator. You will receive a JSON object whose values are '
            . 'parts of a customer product review. Translate every value into ' . $targetLanguage . '. '
            . 'Preserve line breaks within values. Do not translate brand names, model numbers, or URLs. '
            . 'Keep the tone natural and conversational. Respond with ONLY a JSON object using the exact same '
            . 'keys as the input and the translated strings as values. No markdown, no commentary.';
    }

    /**
     * POST JSON and return the decoded response array.
     *
     * @param string $url
     * @param array<string,string> $headers
     * @param array $payload
     * @return array
     * @throws LocalizedException
     */
    protected function request(string $url, array $headers, array $payload): array
    {
        $this->curl->setTimeout(self::TIMEOUT_SECONDS);
        foreach ($headers as $name => $value) {
            $this->curl->addHeader($name, $value);
        }

        try {
            $this->curl->post($url, $this->json->serialize($payload));
        } catch (\Exception $e) {
            $this->logger->error('[ETechFlow Reviews] ' . $this->getProviderLabel()
                . ' request failed: ' . $e->getMessage());
            throw $this->unavailable();
        }

        $status = $this->curl->getStatus();
        $body = $this->curl->getBody();

        if ($status !== 200) {
            $this->logger->error('[ETechFlow Reviews] ' . $this->getProviderLabel()
                . ' API HTTP ' . $status . ': ' . $body);
            throw $this->unavailable();
        }

        try {
            $decoded = $this->json->unserialize($body);
        } catch (\Exception $e) {
            $this->logger->error('[ETechFlow Reviews] ' . $this->getProviderLabel()
                . ' returned an invalid response: ' . $body);
            throw $this->unavailable();
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Parse the model's JSON text into the expected fields.
     *
     * @param string $text
     * @param string[] $expectedKeys
     * @return array<string,string>
     * @throws LocalizedException
     */
    protected function extractTranslatedFields(string $text, array $expectedKeys): array
    {
        $text = trim($text);
        if ($text === '') {
            $this->logger->error('[ETechFlow Reviews] ' . $this->getProviderLabel()
                . ' returned no text.');
            throw $this->unavailable();
        }

        // Strip a ```json fence if the model added one despite instructions.
        $text = (string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text);

        try {
            $decoded = $this->json->unserialize(trim($text));
        } catch (\Exception $e) {
            $this->logger->error('[ETechFlow Reviews] Could not parse '
                . $this->getProviderLabel() . ' translation JSON: ' . $text);
            throw $this->unavailable();
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
