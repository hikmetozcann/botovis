<?php

declare(strict_types=1);

namespace Botovis\Core\Contracts;

/**
 * Communicates with an LLM to resolve user intent.
 *
 * Pluggable: OpenAI, Anthropic, Ollama, or any custom driver.
 */
interface LlmDriverInterface
{
    /**
     * Send a message to the LLM and get a response.
     *
     * @param string $systemPrompt  The system context (schema, rules)
     * @param array  $messages      Conversation history [{role, content}, ...]
     * @return string               The LLM's response
     */
    public function chat(string $systemPrompt, array $messages): string;

    /**
     * Send a message and stream the response token-by-token.
     *
     * @param string   $systemPrompt
     * @param array    $messages
     * @param callable $onToken  Called with each token: fn(string $token): void
     * @return string            The full accumulated response
     */
    public function stream(string $systemPrompt, array $messages, callable $onToken): string;

    /**
     * Get the driver name (e.g. "openai", "anthropic", "ollama").
     */
    public function name(): string;
}
