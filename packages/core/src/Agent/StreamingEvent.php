<?php

declare(strict_types=1);

namespace Botovis\Core\Agent;

/**
 * Represents a Server-Sent Event for agent streaming.
 */
final class StreamingEvent
{
    public const TYPE_STEP = 'step';
    public const TYPE_THINKING = 'thinking';
    public const TYPE_TOOL_CALL = 'tool_call';
    public const TYPE_TOOL_RESULT = 'tool_result';
    public const TYPE_CONFIRMATION = 'confirmation';
    public const TYPE_MESSAGE = 'message';
    public const TYPE_ERROR = 'error';
    public const TYPE_DONE = 'done';

    private function __construct(
        public readonly string $type,
        public readonly array $data,
    ) {}

    /**
     * Agent is thinking/reasoning.
     */
    public static function thinking(int $step, string $thought): self
    {
        return new self(self::TYPE_THINKING, [
            'step' => $step,
            'thought' => $thought,
        ]);
    }

    /**
     * Agent is calling a tool.
     */
    public static function toolCall(int $step, string $tool, array $params): self
    {
        return new self(self::TYPE_TOOL_CALL, [
            'step' => $step,
            'tool' => $tool,
            'params' => $params,
        ]);
    }

    /**
     * Tool returned a result.
     */
    public static function toolResult(int $step, string $tool, string $observation): self
    {
        return new self(self::TYPE_TOOL_RESULT, [
            'step' => $step,
            'tool' => $tool,
            'observation' => $observation,
        ]);
    }

    /**
     * A complete reasoning step.
     */
    public static function step(AgentStep $agentStep): self
    {
        return new self(self::TYPE_STEP, $agentStep->toArray());
    }

    /**
     * Agent needs confirmation for an action.
     */
    public static function confirmation(string $action, array $params, string $description): self
    {
        return new self(self::TYPE_CONFIRMATION, [
            'action' => $action,
            'params' => $params,
            'description' => $description,
        ]);
    }

    /**
     * Final message/answer.
     */
    public static function message(string $content): self
    {
        return new self(self::TYPE_MESSAGE, [
            'content' => $content,
        ]);
    }

    /**
     * An error occurred.
     */
    public static function error(string $message): self
    {
        return new self(self::TYPE_ERROR, [
            'message' => $message,
        ]);
    }

    /**
     * Stream is complete.
     */
    public static function done(array $steps = [], ?string $finalMessage = null): self
    {
        return new self(self::TYPE_DONE, [
            'steps' => $steps,
            'message' => $finalMessage,
        ]);
    }

    /**
     * Convert to SSE format.
     */
    public function toSse(): string
    {
        $json = json_encode($this->data, JSON_UNESCAPED_UNICODE);
        return "event: {$this->type}\ndata: {$json}\n\n";
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}
