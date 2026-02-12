<?php

declare(strict_types=1);

namespace Botovis\Core;

use Botovis\Core\Contracts\ActionExecutorInterface;
use Botovis\Core\Contracts\ActionResult;
use Botovis\Core\Contracts\AuthorizerInterface;
use Botovis\Core\Contracts\ConversationManagerInterface;
use Botovis\Core\Conversation\ConversationState;
use Botovis\Core\DTO\SecurityContext;
use Botovis\Core\Intent\IntentResolver;
use Botovis\Core\Intent\ResolvedIntent;
use Botovis\Core\Enums\IntentType;

/**
 * The Botovis Orchestrator — the brain that ties everything together.
 *
 * Handles the full conversation flow:
 *   User message → Intent Resolution → Authorization → Execution → Response
 *
 * Both the terminal command and HTTP controller delegate to this class.
 * The Orchestrator is framework-agnostic and I/O-agnostic.
 */
class Orchestrator
{
    private const MAX_AUTO_STEPS = 3;

    private ?AuthorizerInterface $authorizer = null;
    private ?SecurityContext $securityContext = null;

    public function __construct(
        private readonly IntentResolver $resolver,
        private readonly ActionExecutorInterface $executor,
        private readonly ConversationManagerInterface $conversationManager,
    ) {}

    /**
     * Set the authorizer for security checks
     */
    public function setAuthorizer(AuthorizerInterface $authorizer): self
    {
        $this->authorizer = $authorizer;
        return $this;
    }

    /**
     * Set security context directly (useful when context is pre-built)
     */
    public function setSecurityContext(SecurityContext $context): self
    {
        $this->securityContext = $context;
        return $this;
    }

    /**
     * Get current security context
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

        // No authorizer = full access
        return new SecurityContext(null, null, ['*'], ['*' => ['*']]);
    }

    /**
     * Process a user message and return a structured response.
     *
     * @param string $conversationId  Unique conversation identifier
     * @param string $userMessage     The natural language message
     * @return OrchestratorResponse
     */
    public function handle(string $conversationId, string $userMessage): OrchestratorResponse
    {
        // Pass security context to resolver for permission-aware prompts
        $this->resolver->setSecurityContext($this->getSecurityContext());

        $conversation = $this->conversationManager->get($conversationId);

        try {
            $response = $this->processMessage($userMessage, $conversation);
        } finally {
            $this->conversationManager->save($conversationId, $conversation);
        }

        return $response;
    }

    /**
     * Confirm a pending action.
     */
    public function confirm(string $conversationId): OrchestratorResponse
    {
        $conversation = $this->conversationManager->get($conversationId);

        try {
            if (!$conversation->hasPendingIntent()) {
                return OrchestratorResponse::error('Onaylanacak bekleyen bir işlem yok.');
            }

            $pending = $conversation->getPendingIntent();
            $result = $this->executor->execute(
                $pending->table,
                $pending->action,
                $pending->data,
                $pending->where,
                $pending->select,
            );
            $conversation->clearPendingIntent();

            $conversation->addUserMessage('evet');
            $conversation->addAssistantMessage($this->buildResultSummary($result));

            return OrchestratorResponse::executed($pending, $result);
        } finally {
            $this->conversationManager->save($conversationId, $conversation);
        }
    }

    /**
     * Reject a pending action.
     */
    public function reject(string $conversationId): OrchestratorResponse
    {
        $conversation = $this->conversationManager->get($conversationId);

        try {
            if (!$conversation->hasPendingIntent()) {
                return OrchestratorResponse::error('Reddedilecek bekleyen bir işlem yok.');
            }

            $conversation->clearPendingIntent();
            $conversation->addUserMessage('hayır');
            $conversation->addAssistantMessage('İşlem iptal edildi.');

            return OrchestratorResponse::rejected();
        } finally {
            $this->conversationManager->save($conversationId, $conversation);
        }
    }

    /**
     * Delete a conversation.
     */
    public function reset(string $conversationId): void
    {
        $this->conversationManager->delete($conversationId);
    }

    // ──────────────────────────────────────────────
    //  Internal
    // ──────────────────────────────────────────────

    private function processMessage(string $input, ConversationState $conversation, int $depth = 0): OrchestratorResponse
    {
        // Check if user is responding to a pending confirmation via text
        if ($conversation->hasPendingIntent()) {
            $pending = $conversation->getPendingIntent();

            if (ConversationState::isConfirmation($input)) {
                $result = $this->executor->execute(
                    $pending->table,
                    $pending->action,
                    $pending->data,
                    $pending->where,
                    $pending->select,
                );
                $conversation->clearPendingIntent();
                $conversation->addUserMessage($input);
                $conversation->addAssistantMessage($this->buildResultSummary($result));

                return OrchestratorResponse::executed($pending, $result);
            }

            if (ConversationState::isRejection($input)) {
                $conversation->clearPendingIntent();
                $conversation->addUserMessage($input);
                $conversation->addAssistantMessage('İşlem iptal edildi.');

                // Check if user added a message after rejection
                $remainder = ConversationState::extractAfterRejection($input);
                if ($remainder !== null) {
                    return $this->processMessage($remainder, $conversation);
                }

                return OrchestratorResponse::rejected();
            }

            // Not a confirmation/rejection → treat as a new message
            $conversation->clearPendingIntent();
        }

        // Resolve intent via LLM
        $intent = $this->resolver->resolve($input, $conversation->getHistory());

        $conversation->addUserMessage($input);
        
        // Add assistant message to history (for context)
        // For actions: brief summary; for questions: the actual response
        if ($intent->isAction()) {
            $conversation->addAssistantMessage(
                "[{$intent->action->value}:{$intent->table}] {$intent->message}"
            );
        } else {
            $conversation->addAssistantMessage($intent->message);
        }

        // Non-action intents (question, clarification) are always allowed
        if (!$intent->isAction()) {
            return OrchestratorResponse::message($intent);
        }

        // Authorization check for action intents
        $authResult = $this->checkAuthorization($intent);
        if (!$authResult->allowed) {
            return OrchestratorResponse::unauthorized(
                $authResult->reason ?? 'Bu işlem için yetkiniz yok.',
                $authResult->suggestion
            );
        }

        // Write action → needs confirmation
        if ($intent->requiresConfirmation()) {
            $conversation->setPendingIntent($intent);
            return OrchestratorResponse::confirmation($intent);
        }

        // READ → execute immediately
        $result = $this->executor->execute(
            $intent->table,
            $intent->action,
            $intent->data,
            $intent->where,
            $intent->select,
        );

        $conversation->addAssistantMessage($this->buildResultSummary($result));

        // Auto-continue chain
        if ($intent->autoContinue && $result->success && $depth < self::MAX_AUTO_STEPS) {
            $nextResponse = $this->processMessage(
                'Sonuçları gördüm, şimdi kullanıcının istediği bir sonraki işleme devam et.',
                $conversation,
                $depth + 1,
            );

            // Prepend this READ step to the response chain
            $nextResponse->prependStep($intent, $result);
            return $nextResponse;
        }

        return OrchestratorResponse::executed($intent, $result);
    }

    /**
     * Check authorization for an intent
     */
    private function checkAuthorization(ResolvedIntent $intent): \Botovis\Core\DTO\AuthorizationResult
    {
        if (!$this->authorizer) {
            return \Botovis\Core\DTO\AuthorizationResult::allow();
        }

        $context = $this->getSecurityContext();
        return $this->authorizer->authorize($intent, $context);
    }

    private function buildResultSummary(ActionResult $result): string
    {
        $summary = $result->success ? "[İşlem başarılı] " : "[İşlem başarısız] ";
        $summary .= $result->message;

        if (!empty($result->data)) {
            $compactData = $result->data;
            if (count($compactData) > 5) {
                $compactData = array_slice($compactData, 0, 5);
                $summary .= "\nSonuç (ilk 5): " . json_encode($compactData, JSON_UNESCAPED_UNICODE);
            } else {
                $summary .= "\nSonuç: " . json_encode($compactData, JSON_UNESCAPED_UNICODE);
            }
        }

        return $summary;
    }
}
