<?php

declare(strict_types=1);

namespace Botovis\Core\Agent;

use Botovis\Core\Contracts\LlmDriverInterface;
use Botovis\Core\DTO\SecurityContext;
use Botovis\Core\Schema\DatabaseSchema;
use Botovis\Core\Tools\ToolRegistry;
use Botovis\Core\Tools\ToolResult;

/**
 * The Agent Loop — implements ReAct (Reasoning + Acting) pattern.
 *
 * Flow:
 * 1. User sends message
 * 2. Agent thinks about what to do (Thought)
 * 3. Agent decides to use a tool (Action)
 * 4. Tool executes and returns result (Observation)
 * 5. Agent thinks again with new info
 * 6. Repeat until agent has enough info to answer
 * 7. Return final answer (or ask for confirmation)
 */
class AgentLoop
{
    private const DEFAULT_MAX_STEPS = 30;

    private ?SecurityContext $securityContext = null;
    
    /** @var callable|null Step callback for streaming: fn(AgentStep $step, AgentState $state) */
    private $stepCallback = null;

    public function __construct(
        private readonly LlmDriverInterface $llm,
        private readonly ToolRegistry $tools,
        private readonly DatabaseSchema $schema,
    ) {}

    /**
     * Set a callback to be called after each step completes.
     * Useful for streaming progress to the client.
     *
     * @param callable $callback fn(AgentStep $step, AgentState $state): void
     */
    public function onStep(callable $callback): self
    {
        $this->stepCallback = $callback;
        return $this;
    }

    public function setSecurityContext(SecurityContext $context): self
    {
        $this->securityContext = $context;
        return $this;
    }

    /**
     * Run the agent loop for a user message.
     *
     * @param string $userMessage The user's natural language request
     * @param array  $history     Previous conversation messages
     * @param int    $maxSteps    Maximum reasoning steps
     * @return AgentState         The final state with answer or pending action
     */
    public function run(string $userMessage, array $history = [], int $maxSteps = self::DEFAULT_MAX_STEPS): AgentState
    {
        $state = new AgentState($userMessage, $maxSteps);

        while ($state->isRunning() && !$state->isMaxStepsReached()) {
            $this->executeStep($state, $history);
        }

        // If max steps reached without completion, fail gracefully
        if ($state->isRunning() && $state->isMaxStepsReached()) {
            $state->fail("Maksimum adım sayısına ulaşıldı. Lütfen sorunuzu daha spesifik hale getirin.");
        }

        return $state;
    }

    /**
     * Run agent loop with streaming - yields each step as it completes.
     *
     * @param string $userMessage
     * @param array  $history
     * @param int    $maxSteps
     * @return \Generator<AgentStep|AgentState> Yields AgentSteps during execution, returns final AgentState
     */
    public function runStreaming(string $userMessage, array $history = [], int $maxSteps = self::DEFAULT_MAX_STEPS): \Generator
    {
        $state = new AgentState($userMessage, $maxSteps);

        while ($state->isRunning() && !$state->isMaxStepsReached()) {
            $step = $this->executeStepAndReturn($state, $history);
            if ($step) {
                yield $step;
            }
        }

        // If max steps reached without completion, fail gracefully
        if ($state->isRunning() && $state->isMaxStepsReached()) {
            $state->fail("Maksimum adım sayısına ulaşıldı. Lütfen sorunuzu daha spesifik hale getirin.");
        }

        return $state;
    }

    /**
     * Execute a single reasoning step.
     */
    private function executeStep(AgentState $state, array $history): void
    {
        $this->executeStepAndReturn($state, $history);
    }

    /**
     * Execute a single reasoning step and return the step (for streaming).
     */
    private function executeStepAndReturn(AgentState $state, array $history): ?AgentStep
    {
        $systemPrompt = $this->buildSystemPrompt($state);
        
        $messages = array_merge($history, [
            ['role' => 'user', 'content' => $state->userMessage],
        ]);

        // Add previous reasoning steps as assistant context
        $traceContext = $state->toPromptContext();
        if ($traceContext) {
            $messages[] = ['role' => 'assistant', 'content' => $traceContext];
            $messages[] = ['role' => 'user', 'content' => 'Continue your reasoning. What is your next thought and action?'];
        }

        $response = $this->llm->chat($systemPrompt, $messages);
        $parsed = $this->parseAgentResponse($response);

        if ($parsed['type'] === 'final_answer') {
            $state->complete($parsed['answer']);
            return null;
        }

        if ($parsed['type'] === 'action') {
            $step = AgentStep::action(
                $state->getCurrentStepNumber(),
                $parsed['thought'],
                $parsed['action'],
                $parsed['params'],
            );

            // Check if action needs confirmation (write operations)
            $tool = $this->tools->get($parsed['action']);
            if ($tool && $tool->requiresConfirmation()) {
                $state->addStep($step);
                $this->notifyStep($step, $state);
                $state->needsConfirmation(
                    $parsed['action'],
                    $parsed['params'],
                    $parsed['thought'],
                );
                return $step;
            }

            // Execute the tool
            $result = $this->tools->execute($parsed['action'], $parsed['params']);
            $step = $step->withObservation($result->toObservation());
            $state->addStep($step);
            $this->notifyStep($step, $state);
            return $step;
        }

        // Unknown response — treat as thought only
        $step = AgentStep::thought($state->getCurrentStepNumber(), $parsed['thought'] ?? $response);
        $state->addStep($step);
        $this->notifyStep($step, $state);
        return $step;
    }

    /**
     * Notify step callback if set.
     */
    private function notifyStep(AgentStep $step, AgentState $state): void
    {
        if ($this->stepCallback !== null) {
            ($this->stepCallback)($step, $state);
        }
    }

    /**
     * Continue after user confirmation.
     */
    public function continueAfterConfirmation(AgentState $state, array $history): AgentState
    {
        $pending = $state->getPendingAction();
        if (!$pending) {
            $state->fail("Onaylanacak bekleyen işlem yok.");
            return $state;
        }

        // Extend max steps so the agent has room to finish after confirmation
        $state->extendMaxSteps(5);

        // Execute the confirmed action
        $result = $this->tools->execute($pending['action'], $pending['params']);

        \Log::info('[Botovis] continueAfterConfirmation', [
            'action' => $pending['action'],
            'success' => $result->success,
            'message' => $result->message,
            'steps_before' => count($state->getSteps()),
            'maxSteps' => $state->maxSteps,
        ]);
        
        // Replace the last step (which had no observation) with observation
        $lastStep = $state->getLastStep();
        if ($lastStep) {
            $prefix = $result->success ? '[CONFIRMED_SUCCESS]' : '[CONFIRMED_FAILED]';
            $observation = $prefix . ' ' . $result->toObservation();
            $updatedStep = $lastStep->withObservation($observation);
            $state->replaceLastStep($updatedStep);
            $this->notifyStep($updatedStep, $state);
        }

        $state->clearPendingAction();

        // Let the agent loop continue so the LLM can summarize in Turkish
        while ($state->isRunning() && !$state->isMaxStepsReached()) {
            $this->executeStep($state, $history);
        }

        // If max steps reached without LLM completing, auto-complete with result
        if ($state->isRunning() && $state->isMaxStepsReached()) {
            if ($result->success) {
                $state->complete($result->message);
            } else {
                $state->fail($result->message);
            }
        }

        return $state;
    }

    /**
     * Build system prompt with tools and schema context.
     */
    private function buildSystemPrompt(AgentState $state): string
    {
        $schemaContext = $this->schema->toPromptContext();
        $toolsContext = $this->tools->toPromptContext();
        $userContext = $this->buildUserContext();

        return <<<PROMPT
You are Botovis, an intelligent AI agent that helps users interact with their database through natural language.

You follow the ReAct (Reasoning + Acting) pattern:
1. **Think** about what the user wants and what information you need
2. **Act** by using available tools to gather data or perform operations
3. **Observe** the results and think again
4. Repeat until you can provide a complete answer

{$userContext}

{$schemaContext}

{$toolsContext}

RESPONSE FORMAT:
Always respond with valid JSON in one of these formats:

**When you need to use a tool:**
```json
{
  "type": "action",
  "thought": "Your reasoning about why you need this tool",
  "action": "tool_name",
  "params": {"param1": "value1"}
}
```

**When you have enough information to answer:**
```json
{
  "type": "final_answer",
  "thought": "Your final reasoning",
  "answer": "Your complete answer to the user. Use markdown for formatting."
}
```

RULES:
1. Always think step by step. Don't try to answer without gathering necessary data first.
2. Use tools to explore and understand the data before making conclusions.
3. If you're unsure about something, use a tool to verify (e.g., get_sample_data to see actual data).
4. For write operations (create_record, update_record, delete_record), always explain what will change.
5. Be concise but complete in your final answers.
6. If the user asks for analysis or opinions, gather relevant data first, then provide insights.
7. NEVER guess column names or values — always verify with tools first.
8. Current step: {$state->getCurrentStepNumber()} of {$state->maxSteps} max steps.
9. ALWAYS respond in Turkish. All your answers, thoughts, and explanations must be in Turkish.
10. When you see [CONFIRMED_SUCCESS] or [CONFIRMED_FAILED] in an observation, it means the user confirmed a write operation and it was executed. You MUST immediately provide a final_answer in Turkish summarizing what happened. If successful, explain what was created/updated/deleted. If failed, explain the error clearly.

IMPORTANT: Respond with ONLY the JSON object. No markdown code fences, no extra text.
PROMPT;
    }

    /**
     * Build user context with permissions.
     */
    private function buildUserContext(): string
    {
        if (!$this->securityContext || $this->securityContext->isGuest()) {
            return "CURRENT USER: Guest (unauthenticated)";
        }

        $ctx = $this->securityContext;
        $lines = ["CURRENT USER:"];
        $lines[] = "- Role: " . ($ctx->userRole ?? 'unknown');
        
        if (!empty($ctx->metadata['user_name'])) {
            $lines[] = "- Name: " . $ctx->metadata['user_name'];
        }

        $tables = $ctx->getAccessibleTables();
        if (in_array('*', $tables, true)) {
            $lines[] = "- Access: Full access to all tables";
        } else {
            $lines[] = "- Accessible tables: " . implode(', ', $tables);
        }

        return implode("\n", $lines);
    }

    /**
     * Parse the agent's JSON response.
     */
    private function parseAgentResponse(string $response): array
    {
        // Strip markdown code fences
        $response = trim($response);
        $response = preg_replace('/^```(?:json)?\s*/i', '', $response);
        $response = preg_replace('/\s*```$/i', '', $response);
        $response = trim($response);

        $parsed = json_decode($response, true);

        if ($parsed === null) {
            // Couldn't parse — treat as thought
            return [
                'type' => 'thought',
                'thought' => $response,
            ];
        }

        return $parsed;
    }
}
