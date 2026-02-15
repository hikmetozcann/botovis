<?php

declare(strict_types=1);

namespace Botovis\Laravel\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Botovis\Core\Orchestrator;
use Botovis\Core\Agent\AgentOrchestrator;
use Botovis\Core\Agent\StreamingEvent;
use Botovis\Core\Contracts\SchemaDiscoveryInterface;
use Botovis\Core\Contracts\ConversationRepositoryInterface;
use Botovis\Core\DTO\Message;
use Botovis\Core\Enums\ActionType;
use Botovis\Laravel\Security\BotovisAuthorizer;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use DateTimeImmutable;

/**
 * HTTP API for the Botovis chat widget.
 *
 * Endpoints:
 *   POST /botovis/chat       → Send a message
 *   POST /botovis/confirm    → Confirm a pending write action
 *   POST /botovis/reject     → Reject a pending write action
 *   POST /botovis/reset      → Reset conversation
 *   GET  /botovis/schema     → Get available tables (for widget UI)
 *   GET  /botovis/status     → Health check
 */
class BotovisController extends Controller
{
    private string $mode;

    public function __construct(
        private readonly Orchestrator $orchestrator,
        private readonly AgentOrchestrator $agentOrchestrator,
        private readonly BotovisAuthorizer $authorizer,
        private readonly ?ConversationRepositoryInterface $conversationRepository = null,
    ) {
        // Inject authorizer into both orchestrators
        $this->orchestrator->setAuthorizer($this->authorizer);
        $this->agentOrchestrator->setAuthorizer($this->authorizer);
        $this->mode = config('botovis.mode', 'agent');
    }

    /**
     * POST /botovis/chat
     *
     * Body: { "message": "string", "conversation_id": "string?" }
     * Response: OrchestratorResponse JSON
     */
    public function chat(Request $request): JsonResponse
    {
        // Check authentication if required
        $authError = $this->checkAuth();
        if ($authError) return $authError;

        $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|string|uuid',
        ]);

        $message = $request->input('message');
        $userId = $this->getUserId();

        // Get or create conversation
        $conversationId = $request->input('conversation_id');
        $isNewConversation = false;

        if ($conversationId && $this->conversationRepository) {
            // Verify ownership
            if (!$this->conversationRepository->belongsToUser($conversationId, $userId)) {
                return response()->json([
                    'type' => 'error',
                    'message' => 'Sohbet bulunamadı.',
                ], 404);
            }
        } elseif ($this->conversationRepository) {
            // Create new conversation
            $conversation = $this->conversationRepository->createConversation($userId, 'Yeni Sohbet');
            $conversationId = $conversation->id;
            $isNewConversation = true;
        } else {
            // No repository - use simple ID
            $conversationId = $this->generateConversationId($request);
        }

        $startTime = microtime(true);

        // Save user message
        if ($this->conversationRepository) {
            $this->conversationRepository->addMessage(Message::user(
                Str::uuid()->toString(),
                $conversationId,
                $message,
            ));
        }

        // Process with appropriate orchestrator based on mode
        if ($this->mode === 'agent') {
            $response = $this->agentOrchestrator->handle($conversationId, $message);
            $responseArray = $response->toArray();
            
            // Add reasoning steps if configured
            if (!config('botovis.agent.show_steps', false)) {
                unset($responseArray['steps']);
            }
        } else {
            $response = $this->orchestrator->handle($conversationId, $message);
            $responseArray = $response->toArray();
        }

        $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        // Save assistant response
        if ($this->conversationRepository) {
            $actionStr = $responseArray['pending_action']['action'] ?? null;
            $actionType = $actionStr ? ActionType::tryFrom($actionStr) : null;
            
            $this->conversationRepository->addMessage(Message::assistant(
                Str::uuid()->toString(),
                $conversationId,
                $responseArray['message'] ?? '',
                intent: $responseArray['type'] ?? null,
                action: $actionType,
                table: $responseArray['pending_action']['params']['table'] ?? null,
                parameters: $responseArray['pending_action']['params'] ?? [],
                success: ($responseArray['type'] ?? '') !== 'error',
                executionTimeMs: $executionTimeMs,
            ));

            // Auto-generate title from first message
            if ($isNewConversation && strlen($message) > 0) {
                $title = mb_substr($message, 0, 50);
                if (mb_strlen($message) > 50) $title .= '...';
                $this->conversationRepository->updateTitle($conversationId, $title);
            }
        }

        return response()->json([
            'conversation_id' => $conversationId,
            ...$responseArray,
        ]);
    }

    /**
     * POST /botovis/confirm
     *
     * Body: { "conversation_id": "string" }
     */
    public function confirm(Request $request): JsonResponse
    {
        $authError = $this->checkAuth();
        if ($authError) return $authError;

        $request->validate([
            'conversation_id' => 'required|string|max:64',
        ]);

        $conversationId = $request->input('conversation_id');
        $startTime = microtime(true);
        
        if ($this->mode === 'agent') {
            $response = $this->agentOrchestrator->confirm($conversationId);
            $responseArray = $response->toArray();
            
            if (!config('botovis.agent.show_steps', false)) {
                unset($responseArray['steps']);
            }
        } else {
            $response = $this->orchestrator->confirm($conversationId);
            $responseArray = $response->toArray();
        }

        // Save confirm action and result to conversation history
        if ($this->conversationRepository) {
            $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->conversationRepository->addMessage(Message::user(
                Str::uuid()->toString(),
                $conversationId,
                'Evet, onayla',
            ));

            $this->conversationRepository->addMessage(Message::assistant(
                Str::uuid()->toString(),
                $conversationId,
                $responseArray['message'] ?? 'İşlem onaylandı.',
                intent: $responseArray['type'] ?? 'executed',
                success: ($responseArray['type'] ?? '') !== 'error',
                executionTimeMs: $executionTimeMs,
            ));
        }

        return response()->json([
            'conversation_id' => $conversationId,
            ...$responseArray,
        ]);
    }

    /**
     * POST /botovis/reject
     *
     * Body: { "conversation_id": "string" }
     */
    public function reject(Request $request): JsonResponse
    {
        $authError = $this->checkAuth();
        if ($authError) return $authError;

        $request->validate([
            'conversation_id' => 'required|string|max:64',
        ]);

        $conversationId = $request->input('conversation_id');
        
        if ($this->mode === 'agent') {
            $response = $this->agentOrchestrator->reject($conversationId);
            $responseArray = $response->toArray();
        } else {
            $response = $this->orchestrator->reject($conversationId);
            $responseArray = $response->toArray();
        }

        // Save reject action to conversation history
        if ($this->conversationRepository) {
            $this->conversationRepository->addMessage(Message::user(
                Str::uuid()->toString(),
                $conversationId,
                'Hayır, iptal et',
            ));

            $this->conversationRepository->addMessage(Message::assistant(
                Str::uuid()->toString(),
                $conversationId,
                $responseArray['message'] ?? 'İşlem iptal edildi.',
                intent: 'rejected',
                success: true,
            ));
        }

        return response()->json([
            'conversation_id' => $conversationId,
            ...$responseArray,
        ]);
    }

    /**
     * POST /botovis/reset
     *
     * Body: { "conversation_id": "string" }
     */
    public function reset(Request $request): JsonResponse
    {
        $authError = $this->checkAuth();
        if ($authError) return $authError;

        $request->validate([
            'conversation_id' => 'required|string|max:64',
        ]);

        $conversationId = $request->input('conversation_id');
        
        if ($this->mode === 'agent') {
            $this->agentOrchestrator->reset($conversationId);
        } else {
            $this->orchestrator->reset($conversationId);
        }

        return response()->json([
            'conversation_id' => $conversationId,
            'type' => 'reset',
            'message' => 'Konuşma sıfırlandı.',
        ]);
    }

    /**
     * GET /botovis/schema
     *
     * Returns the discovered schema for the widget to show capabilities.
     * Filtered based on user permissions.
     */
    public function schema(SchemaDiscoveryInterface $discovery): JsonResponse
    {
        $authError = $this->checkAuth();
        if ($authError) return $authError;

        $schema = $discovery->discover();
        $context = $this->authorizer->buildContext();

        $tables = array_map(function ($table) {
            return [
                'name' => $table->name,
                'label' => $table->label ?? $table->name,
                'allowed_actions' => array_map(fn ($a) => $a->value, $table->allowedActions),
                'columns' => array_map(fn ($c) => [
                    'name' => $c->name,
                    'type' => $c->type->value,
                    'nullable' => $c->nullable,
                ], $table->columns),
            ];
        }, $schema->tables);

        // Filter tables based on user permissions
        $filteredTables = $this->authorizer->filterSchema($tables, $context);

        return response()->json([
            'tables' => $filteredTables,
            'user' => [
                'id' => $context->userId,
                'role' => $context->userRole,
                'authenticated' => $context->isAuthenticated(),
            ],
        ]);
    }

    /**
     * GET /botovis/status
     */
    public function status(): JsonResponse
    {
        $context = $this->authorizer->buildContext();

        return response()->json([
            'status' => 'ok',
            'version' => '0.2.0',
            'mode' => $this->mode,
            'authenticated' => $context->isAuthenticated(),
            'user_role' => $context->userRole,
        ]);
    }

    /**
     * Check if authentication is required and user is authenticated
     */
    private function checkAuth(): ?JsonResponse
    {
        $requireAuth = config('botovis.security.require_auth', true);
        
        if (!$requireAuth) {
            return null;
        }

        $guard = config('botovis.security.guard', 'web');
        $user = Auth::guard($guard)->user();

        if (!$user) {
            return response()->json([
                'type' => 'unauthorized',
                'message' => 'Bu özelliği kullanmak için giriş yapmalısınız.',
            ], 401);
        }

        return null;
    }

    /**
     * Generate a deterministic conversation ID per user session.
     */
    private function generateConversationId(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier() ?? 'guest';
        return 'botovis_' . $userId . '_' . Str::random(8);
    }

    /**
     * Get current user's ID.
     */
    private function getUserId(): ?string
    {
        $guard = config('botovis.security.guard', 'web');
        $user = Auth::guard($guard)->user();

        return $user?->getAuthIdentifier() ? (string) $user->getAuthIdentifier() : null;
    }

    /**
     * POST /botovis/stream
     *
     * Server-Sent Events endpoint for streaming agent responses.
     * Body: { "message": "string", "conversation_id": "string?" }
     */
    public function stream(Request $request): StreamedResponse
    {
        // Check authentication
        $authError = $this->checkAuthForStream($request);
        if ($authError) {
            return $this->streamError($authError);
        }

        $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|string|uuid',
        ]);

        $message = $request->input('message');
        $userId = $this->getUserId();

        // Get or create conversation
        $conversationId = $request->input('conversation_id');
        $isNewConversation = false;

        if ($conversationId && $this->conversationRepository) {
            if (!$this->conversationRepository->belongsToUser($conversationId, $userId)) {
                return $this->streamError('Sohbet bulunamadı.');
            }
        } elseif ($this->conversationRepository) {
            $conversation = $this->conversationRepository->createConversation($userId, 'Yeni Sohbet');
            $conversationId = $conversation->id;
            $isNewConversation = true;
        } else {
            $conversationId = $this->generateConversationId($request);
        }

        // Save user message
        if ($this->conversationRepository) {
            $this->conversationRepository->addMessage(Message::user(
                Str::uuid()->toString(),
                $conversationId,
                $message,
            ));

            if ($isNewConversation && strlen($message) > 0) {
                $title = mb_substr($message, 0, 50);
                if (mb_strlen($message) > 50) $title .= '...';
                $this->conversationRepository->updateTitle($conversationId, $title);
            }
        }

        return $this->createSseResponse(function () use ($conversationId, $message) {
            // First emit conversation_id
            $this->emitSse('init', ['conversation_id' => $conversationId]);

            // Stream agent events
            $startTime = microtime(true);
            $finalMessage = null;
            $isConfirmation = false;
            $confirmDescription = null;

            foreach ($this->agentOrchestrator->stream($conversationId, $message) as $event) {
                $this->emitSse($event->type, $event->data);

                if ($event->type === StreamingEvent::TYPE_MESSAGE) {
                    $finalMessage = $event->data['content'] ?? '';
                }
                if ($event->type === StreamingEvent::TYPE_CONFIRMATION) {
                    $isConfirmation = true;
                    $confirmDescription = $event->data['description'] ?? '';
                }
            }

            // Save assistant response after stream completes
            if ($this->conversationRepository) {
                $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);

                if ($isConfirmation && $confirmDescription) {
                    $this->conversationRepository->addMessage(Message::assistant(
                        Str::uuid()->toString(),
                        $conversationId,
                        $confirmDescription,
                        intent: 'confirmation',
                        executionTimeMs: $executionTimeMs,
                    ));
                } elseif ($finalMessage !== null) {
                    $this->conversationRepository->addMessage(Message::assistant(
                        Str::uuid()->toString(),
                        $conversationId,
                        $finalMessage,
                        executionTimeMs: $executionTimeMs,
                    ));
                }
            }
        });
    }

    /**
     * POST /botovis/stream-confirm
     *
     * Server-Sent Events endpoint for streaming confirmation responses.
     * Body: { "conversation_id": "string" }
     */
    public function streamConfirm(Request $request): StreamedResponse
    {
        $authError = $this->checkAuthForStream($request);
        if ($authError) {
            return $this->streamError($authError);
        }

        $request->validate([
            'conversation_id' => 'required|string|uuid',
        ]);

        $conversationId = $request->input('conversation_id');
        $userId = $this->getUserId();

        if ($this->conversationRepository && !$this->conversationRepository->belongsToUser($conversationId, $userId)) {
            return $this->streamError('Sohbet bulunamadı.');
        }

        return $this->createSseResponse(function () use ($conversationId) {
            $startTime = microtime(true);
            $finalMessage = null;
            $isConfirmation = false;
            $confirmDescription = null;

            foreach ($this->agentOrchestrator->streamConfirm($conversationId) as $event) {
                $this->emitSse($event->type, $event->data);

                if ($event->type === StreamingEvent::TYPE_MESSAGE) {
                    $finalMessage = $event->data['content'] ?? '';
                }
                if ($event->type === StreamingEvent::TYPE_CONFIRMATION) {
                    $isConfirmation = true;
                    $confirmDescription = $event->data['description'] ?? '';
                }
            }

            // Save confirm action and result to conversation history
            if ($this->conversationRepository) {
                $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);

                $this->conversationRepository->addMessage(Message::user(
                    Str::uuid()->toString(),
                    $conversationId,
                    'Evet, onayla',
                ));

                if ($isConfirmation && $confirmDescription) {
                    $this->conversationRepository->addMessage(Message::assistant(
                        Str::uuid()->toString(),
                        $conversationId,
                        $confirmDescription,
                        intent: 'confirmation',
                        executionTimeMs: $executionTimeMs,
                    ));
                } elseif ($finalMessage !== null) {
                    $this->conversationRepository->addMessage(Message::assistant(
                        Str::uuid()->toString(),
                        $conversationId,
                        $finalMessage,
                        intent: 'executed',
                        success: true,
                        executionTimeMs: $executionTimeMs,
                    ));
                }
            }
        });
    }

    /**
     * Create SSE response with proper headers.
     */
    private function createSseResponse(callable $callback): StreamedResponse
    {
        return new StreamedResponse(function () use ($callback) {
            // Extend execution time for streaming (agent may take many steps)
            set_time_limit(120);
            
            // Disable all output buffering for real-time streaming
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            ob_implicit_flush(true);
            
            // Run the callback
            $callback();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    /**
     * Emit a single SSE event.
     */
    private function emitSse(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        
        // Force flush
        flush();
    }

    /**
     * Return an error as SSE stream.
     */
    private function streamError(string $message): StreamedResponse
    {
        return $this->createSseResponse(function () use ($message) {
            $this->emitSse('error', ['message' => $message]);
            $this->emitSse('done', []);
        });
    }

    /**
     * Check auth for streaming endpoint (returns error message or null).
     */
    private function checkAuthForStream(Request $request): ?string
    {
        $requireAuth = config('botovis.security.require_auth', true);
        
        if (!$requireAuth) {
            return null;
        }

        $guard = config('botovis.security.guard', 'web');
        $user = Auth::guard($guard)->user();

        if (!$user) {
            return 'Bu özelliği kullanmak için giriş yapmalısınız.';
        }

        return null;
    }
}
