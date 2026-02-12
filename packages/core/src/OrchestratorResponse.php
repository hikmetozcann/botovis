<?php

declare(strict_types=1);

namespace Botovis\Core;

use Botovis\Core\Contracts\ActionResult;
use Botovis\Core\Intent\ResolvedIntent;

/**
 * Structured response from the Orchestrator.
 *
 * Represents what to show/return to the user after processing a message.
 * The HTTP controller serializes this to JSON; the CLI command renders it in the terminal.
 */
class OrchestratorResponse
{
    /**
     * Response types:
     *   - message:       LLM answered with text (question/clarification/unknown)
     *   - confirmation:  Write action detected, waiting for user approval
     *   - executed:      Action was executed (READ result, or confirmed write)
     *   - rejected:      User rejected the pending action
     *   - error:         Something went wrong
     */
    public function __construct(
        public readonly string $type,
        public readonly ?ResolvedIntent $intent = null,
        public readonly ?ActionResult $result = null,
        public readonly string $message = '',
        /** @var array<array{intent: ResolvedIntent, result: ActionResult}> Auto-continue intermediate steps */
        public array $steps = [],
    ) {}

    public static function message(ResolvedIntent $intent): self
    {
        return new self(
            type: 'message',
            intent: $intent,
            message: $intent->message,
        );
    }

    public static function confirmation(ResolvedIntent $intent): self
    {
        return new self(
            type: 'confirmation',
            intent: $intent,
            message: $intent->toConfirmationMessage(),
        );
    }

    public static function executed(ResolvedIntent $intent, ActionResult $result): self
    {
        return new self(
            type: 'executed',
            intent: $intent,
            result: $result,
            message: $result->message,
        );
    }

    public static function rejected(): self
    {
        return new self(
            type: 'rejected',
            message: 'İşlem iptal edildi.',
        );
    }

    public static function error(string $message): self
    {
        return new self(
            type: 'error',
            message: $message,
        );
    }

    public static function unauthorized(string $reason, ?string $suggestion = null): self
    {
        $message = $reason;
        if ($suggestion) {
            $message .= ' ' . $suggestion;
        }

        return new self(
            type: 'unauthorized',
            message: $message,
        );
    }

    /**
     * Prepend an intermediate auto-continue step.
     */
    public function prependStep(ResolvedIntent $intent, ActionResult $result): void
    {
        array_unshift($this->steps, [
            'intent' => $intent,
            'result' => $result,
        ]);
    }

    /**
     * Serialize for HTTP JSON response.
     */
    public function toArray(): array
    {
        $arr = [
            'type' => $this->type,
            'message' => $this->message,
        ];

        if ($this->intent !== null) {
            $arr['intent'] = $this->intent->toArray();
        }

        if ($this->result !== null) {
            $arr['result'] = $this->result->toArray();
        }

        if (!empty($this->steps)) {
            $arr['steps'] = array_map(fn ($step) => [
                'intent' => $step['intent']->toArray(),
                'result' => $step['result']->toArray(),
            ], $this->steps);
        }

        return $arr;
    }
}
