<?php

declare(strict_types=1);

namespace Botovis\Core\Agent;

/**
 * Tracks the state of an agent's execution.
 *
 * Contains all steps taken (thoughts, actions, observations) and the final result.
 */
final class AgentState
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_NEEDS_CONFIRMATION = 'needs_confirmation';

    /** @var AgentStep[] */
    private array $steps = [];
    private string $status = self::STATUS_RUNNING;
    private ?string $finalAnswer = null;
    private ?array $pendingAction = null;

    public function __construct(
        public readonly string $userMessage,
        public readonly int $maxSteps = 10,
    ) {}

    /**
     * Add a step to the execution trace.
     */
    public function addStep(AgentStep $step): void
    {
        $this->steps[] = $step;
    }

    /**
     * Get all steps.
     *
     * @return AgentStep[]
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Get the last step.
     */
    public function getLastStep(): ?AgentStep
    {
        return $this->steps[count($this->steps) - 1] ?? null;
    }

    /**
     * Get current step number.
     */
    public function getCurrentStepNumber(): int
    {
        return count($this->steps) + 1;
    }

    /**
     * Check if max steps reached.
     */
    public function isMaxStepsReached(): bool
    {
        return count($this->steps) >= $this->maxSteps;
    }

    /**
     * Mark as completed with final answer.
     */
    public function complete(string $answer): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->finalAnswer = $answer;
    }

    /**
     * Mark as failed.
     */
    public function fail(string $reason): void
    {
        $this->status = self::STATUS_FAILED;
        $this->finalAnswer = $reason;
    }

    /**
     * Mark as needing confirmation for a write action.
     */
    public function needsConfirmation(string $action, array $params, string $description): void
    {
        $this->status = self::STATUS_NEEDS_CONFIRMATION;
        $this->pendingAction = [
            'action' => $action,
            'params' => $params,
            'description' => $description,
        ];
    }

    /**
     * Get the pending action that needs confirmation.
     */
    public function getPendingAction(): ?array
    {
        return $this->pendingAction;
    }

    /**
     * Clear pending action (after confirmation/rejection).
     */
    public function clearPendingAction(): void
    {
        $this->pendingAction = null;
        // Reset status to running so the loop can continue
        if ($this->status === self::STATUS_NEEDS_CONFIRMATION) {
            $this->status = self::STATUS_RUNNING;
        }
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function needsUserConfirmation(): bool
    {
        return $this->status === self::STATUS_NEEDS_CONFIRMATION;
    }

    public function getFinalAnswer(): ?string
    {
        return $this->finalAnswer;
    }

    /**
     * Build context for LLM from execution trace.
     */
    public function toPromptContext(): string
    {
        if (empty($this->steps)) {
            return '';
        }

        $lines = ["Previous reasoning steps:"];

        foreach ($this->steps as $step) {
            $lines[] = "";
            $lines[] = "Step {$step->step}:";
            
            if ($step->thought) {
                $lines[] = "Thought: {$step->thought}";
            }
            
            if ($step->action) {
                $params = json_encode($step->actionParams, JSON_UNESCAPED_UNICODE);
                $lines[] = "Action: {$step->action}({$params})";
            }
            
            if ($step->observation) {
                $lines[] = "Observation: {$step->observation}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Convert to array for API response.
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'steps' => array_map(fn ($s) => $s->toArray(), $this->steps),
            'final_answer' => $this->finalAnswer,
            'pending_action' => $this->pendingAction,
        ];
    }
}
