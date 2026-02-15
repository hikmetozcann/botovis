<?php

declare(strict_types=1);

namespace Botovis\Core\Agent;

use Botovis\Core\Contracts\AuthorizerInterface;
use Botovis\Core\Contracts\ConversationManagerInterface;
use Botovis\Core\Contracts\LlmDriverInterface;
use Botovis\Core\DTO\SecurityContext;
use Botovis\Core\Schema\DatabaseSchema;
use Botovis\Core\Tools\ToolRegistry;

/**
 * Agent-based Orchestrator.
 *
 * Uses ReAct pattern: the agent thinks, uses tools, observes results,
 * and iterates until it can provide a complete answer.
 *
 * This replaces the simple single-shot IntentResolver approach.
 */
class AgentOrchestrator
{
    private const MAX_STEPS = 10;

    private ?AuthorizerInterface $authorizer = null;
    private ?SecurityContext $securityContext = null;

    public function __construct(
        private readonly LlmDriverInterface $llm,
        private readonly ToolRegistry $tools,
        private readonly DatabaseSchema $schema,
        private readonly ConversationManagerInterface $conversationManager,
        private readonly string $locale = 'en',
    ) {}

    /**
     * Locale-aware internal messages.
     */
    private const MESSAGES = [
        'en' => [
            'no_pending_confirm' => 'No pending operation to confirm.',
            'no_pending_reject'  => 'No pending operation to reject.',
            'user_confirmed'     => 'Yes, confirm',
            'user_rejected'      => 'No, cancel',
            'operation_cancelled' => 'Operation cancelled.',
        ],
        'tr' => [
            'no_pending_confirm' => 'Onaylanacak bekleyen bir işlem yok.',
            'no_pending_reject'  => 'Reddedilecek bekleyen bir işlem yok.',
            'user_confirmed'     => 'Evet, onayla',
            'user_rejected'      => 'Hayır, iptal et',
            'operation_cancelled' => 'İşlem iptal edildi.',
        ],
    ];

    private function msg(string $key): string
    {
        return self::MESSAGES[$this->locale][$key]
            ?? self::MESSAGES['en'][$key]
            ?? $key;
    }

    /**
     * Set the authorizer for security checks.
     */
    public function setAuthorizer(AuthorizerInterface $authorizer): self
    {
        $this->authorizer = $authorizer;
        return $this;
    }

    /**
     * Set security context directly.
     */
    public function setSecurityContext(SecurityContext $context): self
    {
        $this->securityContext = $context;
        return $this;
    }

    /**
     * Get current security context.
     */
    public function getSecurityContext(): SecurityContext
    {
        if ($this->securityContext) {
            return $this->securityContext;
        }

        if ($this->authorizer) {
            $this->securityContext = $this->authorizer->buildContext();
            return $this->securityContext;
        }

        return new SecurityContext(null, null, ['*'], ['*' => ['*']]);
    }

    /**
     * Process a user message using the agent loop.
     */
    public function handle(string $conversationId, string $userMessage): AgentResponse
    {
        $conversation = $this->conversationManager->get($conversationId);

        try {
            // Create and run agent loop
            $agentLoop = new AgentLoop($this->llm, $this->tools, $this->schema, $this->locale);
            $agentLoop->setSecurityContext($this->getSecurityContext());

            $state = $agentLoop->run($userMessage, $conversation->getHistory(), self::MAX_STEPS);

            // Update conversation with messages
            $conversation->addUserMessage($userMessage);
            
            if ($state->getFinalAnswer()) {
                $conversation->addAssistantMessage($state->getFinalAnswer());
            }

            // If needs confirmation, store pending state
            if ($state->needsUserConfirmation()) {
                $pending = $state->getPendingAction();
                // Store in conversation state for later retrieval
                $conversation->setPendingAgentState($state);
            }

            return AgentResponse::fromState($state);

        } finally {
            $this->conversationManager->save($conversationId, $conversation);
        }
    }

    /**
     * Confirm a pending action.
     */
    public function confirm(string $conversationId): AgentResponse
    {
        $conversation = $this->conversationManager->get($conversationId);

        try {
            $state = $conversation->getPendingAgentState();

            if (!$state || !$state->needsUserConfirmation()) {
                return AgentResponse::error($this->msg('no_pending_confirm'));
            }

            // Create agent loop and continue after confirmation
            $agentLoop = new AgentLoop($this->llm, $this->tools, $this->schema, $this->locale);
            $agentLoop->setSecurityContext($this->getSecurityContext());

            $state = $agentLoop->continueAfterConfirmation($state, $conversation->getHistory());

            $conversation->addUserMessage($this->msg('user_confirmed'));

            if ($state->needsUserConfirmation()) {
                // Agent needs another confirmation — keep pending state
                $conversation->setPendingAgentState($state);
            } else {
                if ($state->getFinalAnswer()) {
                    $conversation->addAssistantMessage($state->getFinalAnswer());
                }
                $conversation->clearPendingAgentState();
            }

            return AgentResponse::fromState($state);

        } finally {
            $this->conversationManager->save($conversationId, $conversation);
        }
    }

    /**
     * Reject a pending action.
     */
    public function reject(string $conversationId): AgentResponse
    {
        $conversation = $this->conversationManager->get($conversationId);

        try {
            if (!$conversation->getPendingAgentState()) {
                return AgentResponse::error($this->msg('no_pending_reject'));
            }

            $conversation->clearPendingAgentState();
            $conversation->addUserMessage($this->msg('user_rejected'));
            $conversation->addAssistantMessage($this->msg('operation_cancelled'));

            return AgentResponse::message($this->msg('operation_cancelled'));

        } finally {
            $this->conversationManager->save($conversationId, $conversation);
        }
    }

    /**
     * Reset conversation.
     */
    public function reset(string $conversationId): void
    {
        $this->conversationManager->delete($conversationId);
    }

    /**
     * Stream agent response using SSE events.
     *
     * @param string $conversationId
     * @param string $userMessage
     * @return \Generator<StreamingEvent>
     */
    public function stream(string $conversationId, string $userMessage): \Generator
    {
        $conversation = $this->conversationManager->get($conversationId);

        try {
            // Create agent loop
            $agentLoop = new AgentLoop($this->llm, $this->tools, $this->schema, $this->locale);
            $agentLoop->setSecurityContext($this->getSecurityContext());

            // Run with streaming - yields each step as it completes
            $generator = $agentLoop->runStreaming($userMessage, $conversation->getHistory(), self::MAX_STEPS);
            
            // Yield each step as it happens
            foreach ($generator as $step) {
                yield StreamingEvent::step($step);
            }

            // Get final state from generator return value
            $state = $generator->getReturn();

            // Handle different end states
            if ($state->needsUserConfirmation()) {
                $pending = $state->getPendingAction();
                $conversation->setPendingAgentState($state);

                yield StreamingEvent::confirmation(
                    $pending['action'],
                    $pending['params'],
                    $pending['description'],
                );
            } elseif ($state->getFinalAnswer()) {
                yield StreamingEvent::message($state->getFinalAnswer());
            } elseif ($state->getError()) {
                yield StreamingEvent::error($state->getError());
            }

            // Update conversation
            $conversation->addUserMessage($userMessage);
            if ($state->getFinalAnswer()) {
                $conversation->addAssistantMessage($state->getFinalAnswer());
            }

            // Final done event with all steps
            yield StreamingEvent::done(
                array_map(fn(AgentStep $s) => $s->toArray(), $state->getSteps()),
                $state->getFinalAnswer()
            );

        } catch (\Throwable $e) {
            yield StreamingEvent::error($e->getMessage());
            yield StreamingEvent::done();
        } finally {
            $this->conversationManager->save($conversationId, $conversation);
        }
    }

    /**
     * Stream confirmation response.
     *
     * @return \Generator<StreamingEvent>
     */
    public function streamConfirm(string $conversationId): \Generator
    {
        $conversation = $this->conversationManager->get($conversationId);
        $events = [];

        try {
            $state = $conversation->getPendingAgentState();

            if (!$state || !$state->needsUserConfirmation()) {
                yield StreamingEvent::error($this->msg('no_pending_confirm'));
                yield StreamingEvent::done();
                return;
            }

            $agentLoop = new AgentLoop($this->llm, $this->tools, $this->schema, $this->locale);
            $agentLoop->setSecurityContext($this->getSecurityContext());

            $agentLoop->onStep(function (AgentStep $step, AgentState $state) use (&$events) {
                $events[] = StreamingEvent::step($step);
            });

            $state = $agentLoop->continueAfterConfirmation($state, $conversation->getHistory());

            foreach ($events as $event) {
                yield $event;
            }

            if ($state->needsUserConfirmation()) {
                // Agent needs another confirmation — yield it and keep pending state
                $pending = $state->getPendingAction();
                yield StreamingEvent::confirmation(
                    $pending['action'],
                    $pending['params'],
                    $pending['description'],
                );
                $conversation->addUserMessage($this->msg('user_confirmed'));
                $conversation->setPendingAgentState($state);
            } elseif ($state->getFinalAnswer()) {
                yield StreamingEvent::message($state->getFinalAnswer());
                $conversation->addUserMessage($this->msg('user_confirmed'));
                $conversation->addAssistantMessage($state->getFinalAnswer());
                $conversation->clearPendingAgentState();
            } elseif ($state->getError()) {
                yield StreamingEvent::error($state->getError());
                $conversation->addUserMessage($this->msg('user_confirmed'));
                $conversation->clearPendingAgentState();
            } else {
                $conversation->addUserMessage($this->msg('user_confirmed'));
                $conversation->clearPendingAgentState();
            }

            yield StreamingEvent::done(
                array_map(fn(AgentStep $s) => $s->toArray(), $state->getSteps()),
                $state->getFinalAnswer()
            );

        } catch (\Throwable $e) {
            yield StreamingEvent::error($e->getMessage());
            yield StreamingEvent::done();
        } finally {
            $this->conversationManager->save($conversationId, $conversation);
        }
    }
}
