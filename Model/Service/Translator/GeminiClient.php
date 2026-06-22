<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Service\Translator;

/**
 * Translation client for the Google Gemini (Generative Language) API.
 *
 * @see https://ai.google.dev/api/generate-content
 */
class GeminiClient extends AbstractTranslator
{
    private const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /**
     * @inheritDoc
     */
    protected function callModel(array $fields, string $targetLanguage, string $apiKey, string $model): string
    {
        $url = sprintf(self::API_ENDPOINT, rawurlencode($model));

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $this->buildSystemPrompt($targetLanguage)]],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $this->json->serialize($fields)]],
                ],
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'temperature' => 0.2,
                'maxOutputTokens' => self::MAX_TOKENS,
            ],
        ];

        $response = $this->request(
            $url,
            [
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey,
            ],
            $payload
        );

        $text = '';
        foreach ($response['candidates'][0]['content']['parts'] ?? [] as $part) {
            $text .= $part['text'] ?? '';
        }
        return $text;
    }

    /**
     * @inheritDoc
     */
    protected function getProviderLabel(): string
    {
        return 'Gemini';
    }
}
