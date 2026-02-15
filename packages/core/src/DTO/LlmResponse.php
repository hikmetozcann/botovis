<?php

declare(strict_types=1);

namespace Botovis\Core\DTO;

/**
 * Structured response from an LLM — either a text message or a tool call.
 *
 * When using native tool calling APIs, the LLM returns structured tool calls
 * instead of raw text that needs JSON parsing.
 */
class LlmResponse
{
    private function __construct(
        public readonly string $type,       // 'text' | 'tool_call'
        public readonly ?string $text,      // Text content (for text responses or thoughts)
        public readonly ?string $toolName,  // Tool name (for tool calls)
        public readonly ?array $toolParams, // Tool parameters (for tool calls)
        public readonly ?string $toolCallId, // Provider's tool call ID (for tool_result messages)
        public readonly ?string $thought,   // Extracted thought/reasoning
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
        );
    }

    /**
     * Tool call response — LLM wants to invoke a tool.
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
}
