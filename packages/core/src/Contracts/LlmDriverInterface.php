<?php

declare(strict_types=1);

namespace Botovis\Core\Contracts;

use Botovis\Core\DTO\LlmResponse;

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
     * Send a message with tool definitions — LLM can respond with text or a tool call.
     *
     * Uses the provider's native tool/function calling API for structured responses.
     * Eliminates JSON parsing issues and provides more reliable tool invocation.
     *
     * @param string $systemPrompt  The system context
     * @param array  $messages      Conversation history (supports tool_result messages)
     * @param array  $tools         Tool definitions (OpenAI function calling format)
     * @return LlmResponse          Structured response — either text or tool_call
     */
    public function chatWithTools(string $systemPrompt, array $messages, array $tools): LlmResponse;

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
