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
 * Admin dropdown of supported AI translation providers.
 */
class TranslationProvider implements OptionSourceInterface
{
    /**
     * @return array<int,array{value:string,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'openai', 'label' => __('OpenAI (ChatGPT)')],
            ['value' => 'gemini', 'label' => __('Google Gemini')],
            ['value' => 'anthropic', 'label' => __('Anthropic (Claude)')],
        ];
    }
}
