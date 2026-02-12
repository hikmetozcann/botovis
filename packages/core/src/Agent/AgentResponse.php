<?php

declare(strict_types=1);

namespace Botovis\Core\Agent;

/**
 * Response from the Agent system.
 *
 * Contains the final answer, reasoning steps, and any pending actions.
 */
final class AgentResponse
{
    public const TYPE_MESSAGE = 'message';
    public const TYPE_CONFIRMATION = 'confirmation';
    public const TYPE_ERROR = 'error';
    public const TYPE_EXECUTED = 'executed';

    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly array $steps = [],
        public readonly ?array $pendingAction = null,
        public readonly ?array $result = null,
    ) {}

    /**
     * Create a message response (final answer).
     */
    public static function message(string $message, array $steps = []): self
    {
        return new self(
            type: self::TYPE_MESSAGE,
            message: $message,
            steps: $steps,
        );
    }

    /**
     * Create a confirmation response (needs user approval).
     */
    public static function confirmation(string $message, array $pendingAction, array $steps = []): self
    {
        return new self(
            type: self::TYPE_CONFIRMATION,
            message: $message,
            steps: $steps,
            pendingAction: $pendingAction,
        );
    }

    /**
     * Create an error response.
     */
    public static function error(string $message, array $steps = []): self
    {
        return new self(
            type: self::TYPE_ERROR,
            message: $message,
            steps: $steps,
        );
    }

    /**
     * Create an executed response (action completed).
     */
    public static function executed(string $message, array $result, array $steps = []): self
    {
        return new self(
            type: self::TYPE_EXECUTED,
            message: $message,
            steps: $steps,
            result: $result,
        );
    }

    /**
     * Build from AgentState.
     */
    public static function fromState(AgentState $state): self
    {
        $steps = array_map(fn ($s) => $s->toArray(), $state->getSteps());

        if ($state->needsUserConfirmation()) {
            $pending = $state->getPendingAction();
            if ($pending === null) {
                // Edge case: status says needs confirmation but no pending action
                return self::error('Onaylanacak bekleyen işlem bulunamadı.', $steps);
            }
            return self::confirmation(
                $pending['description'] ?? 'Bu işlemi onaylıyor musunuz?',
                $pending,
                $steps,
            );
        }

        if ($state->isFailed()) {
            return self::error(
                $state->getFinalAnswer() ?? 'Bir hata oluştu.',
                $steps,
            );
        }

        return self::message(
            $state->getFinalAnswer() ?? '',
            $steps,
        );
    }

    /**
     * Convert to array for JSON response.
     */
    public function toArray(): array
    {
        $result = array_filter([
            'type' => $this->type,
            'message' => $this->message,
            'steps' => $this->steps ?: null,
            'pending_action' => $this->pendingAction,
            'result' => $this->result,
        ], fn ($v) => $v !== null);

        // Generate intent for confirmation (widget expects this format)
        if ($this->type === self::TYPE_CONFIRMATION && $this->pendingAction) {
            $result['intent'] = [
                'type' => 'confirmation',
                'action' => $this->pendingAction['action'] ?? null,
                'table' => $this->pendingAction['params']['table'] ?? null,
                'data' => $this->pendingAction['params'] ?? [],
                'where' => $this->pendingAction['params']['where'] ?? [],
                'select' => [],
                'message' => $this->message,
                'confidence' => 1.0,
                'auto_continue' => false,
            ];
        }

        return $result;
    }
}
