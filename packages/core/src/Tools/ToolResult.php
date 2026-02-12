<?php

declare(strict_types=1);

namespace Botovis\Core\Tools;

/**
 * Result of a tool execution.
 *
 * Contains the output data and metadata about the execution.
 * This is what the agent "observes" after using a tool.
 */
final class ToolResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly mixed $data = null,
        public readonly ?string $error = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a successful result with data.
     */
    public static function ok(string $message, mixed $data = null, array $metadata = []): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
            metadata: $metadata,
        );
    }

    /**
     * Create a failed result with error message.
     */
    public static function fail(string $error, array $metadata = []): self
    {
        return new self(
            success: false,
            message: $error,
            error: $error,
            metadata: $metadata,
        );
    }

    /**
     * Convert to array for LLM context.
     * This is what the agent sees as "Observation".
     */
    public function toObservation(): string
    {
        if (!$this->success) {
            return "Error: {$this->error}";
        }

        $output = $this->message;

        if ($this->data !== null) {
            if (is_array($this->data)) {
                $output .= "\n" . json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } else {
                $output .= "\n" . (string) $this->data;
            }
        }

        return $output;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return array_filter([
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'error' => $this->error,
            'metadata' => $this->metadata ?: null,
        ], fn ($v) => $v !== null);
    }
}
