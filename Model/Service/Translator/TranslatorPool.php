<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Service\Translator;

/**
 * Resolves the active translation client by configured provider code.
 */
class TranslatorPool
{
    /**
     * @param OpenAiClient $openAi
     * @param GeminiClient $gemini
     * @param ClaudeClient $anthropic
     */
    public function __construct(
        private readonly OpenAiClient $openAi,
        private readonly GeminiClient $gemini,
        private readonly ClaudeClient $anthropic
    ) {
    }

    /**
     * @param string $provider Provider code: openai | gemini | anthropic
     * @return TranslatorInterface
     */
    public function get(string $provider): TranslatorInterface
    {
        return match (strtolower(trim($provider))) {
            'gemini' => $this->gemini,
            'anthropic', 'claude' => $this->anthropic,
            default => $this->openAi,
        };
    }
}
