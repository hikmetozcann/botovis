<?php

declare(strict_types=1);

namespace Botovis\Laravel\Llm;

use Botovis\Core\Contracts\LlmDriverInterface;

/**
 * Creates the appropriate LLM driver based on configuration.
 */
class LlmDriverFactory
{
    /**
     * @param array $config The full botovis.llm config array
     */
    public static function make(array $config): LlmDriverInterface
    {
        $driver = $config['driver'] ?? 'openai';

        return match ($driver) {
            'openai' => new OpenAiDriver(
                apiKey: $config['openai']['api_key'] ?? '',
                model: $config['openai']['model'] ?? 'gpt-4o',
                baseUrl: $config['openai']['base_url'] ?? 'https://api.openai.com/v1',
            ),
            'anthropic' => new AnthropicDriver(
                apiKey: $config['anthropic']['api_key'] ?? '',
                model: $config['anthropic']['model'] ?? 'claude-sonnet-4-20250514',
            ),
            'ollama' => new OllamaDriver(
                model: $config['ollama']['model'] ?? 'llama3',
                baseUrl: $config['ollama']['base_url'] ?? 'http://localhost:11434',
            ),
            default => throw new \InvalidArgumentException(
                "Unknown Botovis LLM driver: '{$driver}'. Supported: openai, anthropic, ollama"
            ),
        };
    }
}
