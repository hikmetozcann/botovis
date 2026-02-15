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

    /**
     * Tool-calling conversation messages (normalized format).
     * Used to maintain multi-turn context for native tool calling APIs.
     *
     * Format:
     * - Tool call:   ['role' => 'assistant', 'content' => ?thought, 'tool_call' => ['id' => ..., 'name' => ..., 'params' => ...]]
     * - Tool result:  ['role' => 'tool_result', 'tool_call_id' => ..., 'content' => ...]
     * @var array
     */
    private array $toolMessages = [];

    public function __construct(
        public readonly string $userMessage,
        public int $maxSteps = 10,
    ) {}

    /**
     * Extend max steps (e.g., after confirmation needs more room).
     */
    public function extendMaxSteps(int $extra): void
    {
        $this->maxSteps += $extra;
    }

    /**
     * Add a step to the execution trace.
     */
    public function addStep(AgentStep $step): void
    {
        $this->steps[] = $step;
    }

    /**
     * Replace the last step (e.g., to add observation after confirmation).
     */
    public function replaceLastStep(AgentStep $step): void
    {
        $idx = count($this->steps) - 1;
        if ($idx >= 0) {
            $this->steps[$idx] = $step;
        } else {
            $this->steps[] = $step;
        }
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

    // ──────────────────────────────────────────────
    //  Tool Calling Messages (for native API)
    // ──────────────────────────────────────────────

    /**
     * Add an assistant tool call message (LLM wants to invoke a tool).
     */
    public function addToolCallMessage(string $toolCallId, string $toolName, array $params, ?string $thought = null): void
    {
        $this->toolMessages[] = [
            'role' => 'assistant',
            'content' => $thought,
            'tool_call' => [
                'id' => $toolCallId,
                'name' => $toolName,
                'params' => $params,
            ],
        ];
    }

    /**
     * Add a tool result message (result of executing a tool).
     */
    public function addToolResultMessage(string $toolCallId, string $content): void
    {
        $this->toolMessages[] = [
            'role' => 'tool_result',
            'tool_call_id' => $toolCallId,
            'content' => $content,
        ];
    }

    /**
     * Replace an existing tool result message (e.g., after confirmation replaces a pending result).
     * If not found, adds it as new.
     */
    public function replaceToolResultMessage(string $toolCallId, string $content): void
    {
        foreach ($this->toolMessages as &$msg) {
            if (($msg['role'] ?? '') === 'tool_result' && ($msg['tool_call_id'] ?? '') === $toolCallId) {
                $msg['content'] = $content;
                return;
            }
        }
        // Not found — add as new
        $this->addToolResultMessage($toolCallId, $content);
    }

    /**
     * Get all tool messages for the multi-turn conversation.
     */
    public function getToolMessages(): array
    {
        return $this->toolMessages;
    }

    // ──────────────────────────────────────────────
    //  Confirmation
    // ──────────────────────────────────────────────

    /**
     * Mark as needing confirmation for a write action.
     */
    public function needsConfirmation(string $action, array $params, string $description, ?string $toolCallId = null): void
    {
        $this->status = self::STATUS_NEEDS_CONFIRMATION;
        $this->pendingAction = [
            'action' => $action,
            'params' => $params,
            'description' => $description,
            'tool_call_id' => $toolCallId,
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
     * Get error message (finalAnswer when status is failed).
     */
    public function getError(): ?string
    {
        return $this->status === self::STATUS_FAILED ? $this->finalAnswer : null;
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
                // Check if this was a user-confirmed action
                if (str_starts_with($step->observation, '[USER CONFIRMED] ')) {
                    $lines[] = "User Confirmation: The user approved this action.";
                    $lines[] = "Observation: " . substr($step->observation, strlen('[USER CONFIRMED] '));
                } else {
                    $lines[] = "Observation: {$step->observation}";
                }
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
