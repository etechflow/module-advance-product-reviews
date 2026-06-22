<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Service\Translator;

/**
 * Translation client for the OpenAI Chat Completions API.
 *
 * @see https://platform.openai.com/docs/api-reference/chat
 */
class OpenAiClient extends AbstractTranslator
{
    private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * @inheritDoc
     */
    protected function callModel(array $fields, string $targetLanguage, string $apiKey, string $model): string
    {
        $payload = [
            'model' => $model,
            'temperature' => 0.2,
            'max_tokens' => self::MAX_TOKENS,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $this->buildSystemPrompt($targetLanguage)],
                ['role' => 'user', 'content' => $this->json->serialize($fields)],
            ],
        ];

        $response = $this->request(
            self::API_ENDPOINT,
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            $payload
        );

        return (string) ($response['choices'][0]['message']['content'] ?? '');
    }

    /**
     * @inheritDoc
     */
    protected function getProviderLabel(): string
    {
        return 'OpenAI';
    }
}
