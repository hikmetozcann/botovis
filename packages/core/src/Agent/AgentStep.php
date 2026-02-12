<?php

declare(strict_types=1);

namespace Botovis\Core\Agent;

/**
 * Represents a single step in the agent's reasoning process.
 *
 * ReAct pattern: Thought → Action → Observation
 */
final class AgentStep
{
    public function __construct(
        public readonly int $step,
        public readonly ?string $thought,
        public readonly ?string $action,
        public readonly ?array $actionParams,
        public readonly ?string $observation,
        public readonly float $timestamp,
    ) {}

    public static function thought(int $step, string $thought): self
    {
        return new self(
            step: $step,
            thought: $thought,
            action: null,
            actionParams: null,
            observation: null,
            timestamp: microtime(true),
        );
    }

    public static function action(int $step, string $thought, string $action, array $params): self
    {
        return new self(
            step: $step,
            thought: $thought,
            action: $action,
            actionParams: $params,
            observation: null,
            timestamp: microtime(true),
        );
    }

    public function withObservation(string $observation): self
    {
        return new self(
            step: $this->step,
            thought: $this->thought,
            action: $this->action,
            actionParams: $this->actionParams,
            observation: $observation,
            timestamp: $this->timestamp,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'step' => $this->step,
            'thought' => $this->thought,
            'action' => $this->action,
            'action_params' => $this->actionParams,
            'observation' => $this->observation,
        ], fn ($v) => $v !== null);
    }
}
