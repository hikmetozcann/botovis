<?php

declare(strict_types=1);

namespace Botovis\Core\Agent;

use Botovis\Core\Contracts\LlmDriverInterface;
use Botovis\Core\DTO\LlmResponse;
use Botovis\Core\DTO\SecurityContext;
use Botovis\Core\Schema\DatabaseSchema;
use Botovis\Core\Tools\ToolRegistry;
use Botovis\Core\Tools\ToolResult;

/**
 * The Agent Loop — implements ReAct (Reasoning + Acting) pattern
 * using native LLM tool calling APIs.
 *
 * Flow:
 * 1. User sends message
 * 2. LLM decides to call a tool (via native tool calling API)
 * 3. Tool executes and returns result
 * 4. Result is sent back to LLM as tool_result message
 * 5. LLM thinks again with new info
 * 6. Repeat until LLM responds with text (final answer) or asks for confirmation
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
        private readonly string $locale = 'en',
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

        if ($state->isRunning() && $state->isMaxStepsReached()) {
            $state->fail("Max steps reached. Please make your question more specific.");
        }

        return $state;
    }

    /**
     * Run agent loop with streaming - yields each step as it completes.
     *
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

        if ($state->isRunning() && $state->isMaxStepsReached()) {
            $state->fail("Max steps reached. Please make your question more specific.");
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
     * Execute a single reasoning step using native tool calling.
     *
     * Supports parallel tool calls — when the LLM returns multiple tool calls
     * in a single response, all are executed within one step (saving step budget).
     *
     * Generate stopping: on the last available step, tools are removed so the
     * LLM is forced to produce a text response with whatever data it has.
     */
    private function executeStepAndReturn(AgentState $state, array $history): ?AgentStep
    {
        $systemPrompt = $this->buildSystemPrompt($state);

        // Generate stopping: on last step, force text response by removing tools
        $stepsRemaining = $state->maxSteps - count($state->getSteps());
        $toolDefs = $stepsRemaining <= 1 ? [] : $this->tools->toFunctionDefinitions();

        // Build messages: conversation history + user message + tool calling messages
        $messages = array_merge(
            $history,
            [['role' => 'user', 'content' => $state->userMessage]],
            $state->getToolMessages(),
        );

        $response = $this->llm->chatWithTools($systemPrompt, $messages, $toolDefs);

        // Text response = final answer
        if ($response->isText()) {
            $state->complete($response->text);
            return null;
        }

        // Tool call response — handle single or parallel
        if ($response->isToolCall()) {
            return $this->handleToolCalls($response, $state);
        }

        return null;
    }

    /**
     * Handle one or more tool calls from a single LLM response.
     *
     * For parallel calls:
     * - All tool call messages are added to state first (required by APIs)
     * - Read tools are executed immediately
     * - Write tools that need confirmation get a "[PENDING]" result
     * - If any tool needs confirmation, the loop pauses
     * - All results count as a single step
     */
    private function handleToolCalls(LlmResponse $response, AgentState $state): ?AgentStep
    {
        $toolCalls = $response->toolCalls;
        $thought = $response->thought ?? '';

        // 1. Add ALL tool call messages to state first
        //    (APIs require balanced tool_call / tool_result pairs)
        foreach ($toolCalls as $i => $tc) {
            $state->addToolCallMessage(
                $tc['id'],
                $tc['name'],
                $tc['params'],
                $i === 0 ? $thought : null,  // thought only on first
            );
        }

        // 2. Process each tool call
        $observations = [];
        $confirmationTool = null;

        foreach ($toolCalls as $tc) {
            $tool = $this->tools->get($tc['name']);

            if ($tool && $tool->requiresConfirmation()) {
                // Write tool — don't execute, mark pending
                $state->addToolResultMessage(
                    $tc['id'],
                    '[PENDING] This operation requires user confirmation before execution.',
                );
                if (!$confirmationTool) {
                    $confirmationTool = $tc;
                }
                $observations[] = "[PENDING confirmation] {$tc['name']}";
            } else {
                // Read tool — execute immediately
                $result = $this->tools->execute($tc['name'], $tc['params']);
                $state->addToolResultMessage($tc['id'], $result->toObservation());
                $observations[] = $result->toObservation();
            }
        }

        // 3. Build step for UI
        $actionNames = array_map(fn($tc) => $tc['name'], $toolCalls);
        $actionLabel = count($actionNames) > 1
            ? implode(', ', $actionNames)
            : $actionNames[0];

        $step = AgentStep::action(
            $state->getCurrentStepNumber(),
            $thought,
            $actionLabel,
            $toolCalls[0]['params'],
        );

        // 4. If any tool needs confirmation, pause
        if ($confirmationTool) {
            $state->addStep($step);
            $this->notifyStep($step, $state);
            $state->needsConfirmation(
                $confirmationTool['name'],
                $confirmationTool['params'],
                $thought,
                $confirmationTool['id'],
            );
            return $step;
        }

        // 5. All tools executed — combine observations
        $combinedObs = implode("\n---\n", $observations);
        $step = $step->withObservation($combinedObs);
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
            $state->fail("No pending operation to confirm.");
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

        // Add tool result to conversation messages (replaces [PENDING] placeholder if exists)
        $toolCallId = $pending['tool_call_id'] ?? ('confirmed_' . uniqid());
        $prefix = $result->success ? '[CONFIRMED_SUCCESS]' : '[CONFIRMED_FAILED]';
        $observation = $prefix . ' ' . $result->toObservation();

        $state->replaceToolResultMessage($toolCallId, $observation);
        
        // Replace the last step (which had no observation) with observation
        $lastStep = $state->getLastStep();
        if ($lastStep) {
            $updatedStep = $lastStep->withObservation($observation);
            $state->replaceLastStep($updatedStep);
            $this->notifyStep($updatedStep, $state);
        }

        $state->clearPendingAction();

        // Let the agent loop continue so the LLM can summarize
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
     * Build system prompt.
     *
     * Tools are NOT described here — they're passed via the native tool calling API.
     * This prompt focuses on behavior, rules, and database schema context.
     */
    private function buildSystemPrompt(AgentState $state): string
    {
        $schemaContext = $this->schema->toPromptContext();
        $userContext = $this->buildUserContext();
        $currentStep = $state->getCurrentStepNumber();
        $maxSteps = $state->maxSteps;
        $stepsRemaining = $maxSteps - count($state->getSteps());

        // Generate stopping: warn the LLM when steps are running low
        $urgency = '';
        if ($stepsRemaining <= 1) {
            $urgency = "\n\nCRITICAL: This is your LAST step. You MUST provide your final answer NOW using all the data you have gathered so far. Do NOT attempt to call any more tools. Summarize your findings and respond to the user.";
        } elseif ($stepsRemaining <= 3) {
            $urgency = "\n\nWARNING: You only have {$stepsRemaining} steps remaining. Wrap up your analysis and provide your answer soon. Only call tools if absolutely essential.";
        }

        return <<<PROMPT
You are Botovis, an intelligent AI agent that helps users interact with their database through natural language.

You have access to tools that let you search, count, aggregate, and modify database records. Use them to gather information before answering.

{$userContext}

{$schemaContext}

RULES:
1. Always think step by step. Don't try to answer without gathering necessary data first.
2. Use tools to explore and understand the data before making conclusions.
3. If you're unsure about something, use a tool to verify (e.g., get_sample_data to see actual data).
4. For write operations (create_record, update_record, delete_record), always explain what will change.
5. Be concise but complete in your final answers. Use markdown for formatting tables and lists.
6. If the user asks for analysis or opinions, gather relevant data first, then provide insights.
7. NEVER guess column names or values — always verify with tools first.
8. Current step: {$currentStep} of {$maxSteps} max steps.
9. ALWAYS respond in the same language the user writes in. Match their language exactly.
10. When you see [CONFIRMED_SUCCESS] or [CONFIRMED_FAILED] in a tool result, it means the user confirmed a write operation and it was executed. Provide a clear summary of what happened.
11. When you need data from multiple tables or multiple counts, call all the tools at once in parallel instead of one by one. This saves steps.{$urgency}
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
}
