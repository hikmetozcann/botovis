<?php

declare(strict_types=1);

namespace Botovis\Core\DTO;

/**
 * Structured response from an LLM — either a text message or one or more tool calls.
 *
 * When using native tool calling APIs, the LLM returns structured tool calls
 * instead of raw text that needs JSON parsing.
 * Supports parallel tool calls (multiple tools invoked in a single LLM turn).
 */
class LlmResponse
{
    /**
     * @param string  $type       'text' | 'tool_call'
     * @param string|null  $text  Text content (for text responses or thoughts)
     * @param string|null  $toolName  First tool name (for backward compat)
     * @param array|null   $toolParams  First tool parameters
     * @param string|null  $toolCallId  First tool call ID
     * @param string|null  $thought  Extracted thought/reasoning
     * @param array  $toolCalls  All tool calls: [['id'=>..., 'name'=>..., 'params'=>[...]]]
     */
    private function __construct(
        public readonly string $type,
        public readonly ?string $text,
        public readonly ?string $toolName,
        public readonly ?array $toolParams,
        public readonly ?string $toolCallId,
        public readonly ?string $thought,
        public readonly array $toolCalls = [],
    ) {}

    /**
     * Text-only response (final answer or thought).
     */
    public static function text(string $text): self
    {
        return new self(
            type: 'text',
            text: $text,
            toolName: null,
            toolParams: null,
            toolCallId: null,
            thought: null,
            toolCalls: [],
        );
    }

    /**
     * Single tool call response — LLM wants to invoke one tool.
     */
    public static function toolCall(
        string $toolName,
        array $toolParams,
        string $toolCallId,
        ?string $thought = null,
    ): self {
        return new self(
            type: 'tool_call',
            text: null,
            toolName: $toolName,
            toolParams: $toolParams,
            toolCallId: $toolCallId,
            thought: $thought,
            toolCalls: [['id' => $toolCallId, 'name' => $toolName, 'params' => $toolParams]],
        );
    }

    /**
     * Multiple parallel tool calls — LLM wants to invoke several tools at once.
     *
     * @param array $calls [['id' => string, 'name' => string, 'params' => array], ...]
     */
    public static function parallelToolCalls(array $calls, ?string $thought = null): self
    {
        $first = $calls[0] ?? null;

        return new self(
            type: 'tool_call',
            text: null,
            toolName: $first['name'] ?? null,
            toolParams: $first['params'] ?? null,
            toolCallId: $first['id'] ?? null,
            thought: $thought,
            toolCalls: $calls,
        );
    }

    public function isToolCall(): bool
    {
        return $this->type === 'tool_call';
    }

    public function isText(): bool
    {
        return $this->type === 'text';
    }

    /**
     * Whether this response contains multiple parallel tool calls.
     */
    public function hasParallelToolCalls(): bool
    {
        return count($this->toolCalls) > 1;
    }
}
