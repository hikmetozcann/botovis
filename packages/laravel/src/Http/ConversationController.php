<?php

declare(strict_types=1);

namespace Botovis\Laravel\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Botovis\Core\Contracts\ConversationRepositoryInterface;
use Botovis\Core\DTO\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * HTTP API for conversation management.
 *
 * Endpoints:
 *   GET    /botovis/conversations              → List user's conversations
 *   POST   /botovis/conversations              → Create new conversation
 *   GET    /botovis/conversations/{id}         → Get conversation with messages
 *   DELETE /botovis/conversations/{id}         → Delete conversation
 *   PATCH  /botovis/conversations/{id}/title   → Update conversation title
 */
class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationRepositoryInterface $repository,
    ) {}

    /**
     * GET /botovis/conversations
     *
     * Query: { limit?: number, offset?: number }
     */
    public function index(Request $request): JsonResponse
    {
        $authError = $this->checkAuth();
        if ($authError) return $authError;

        $userId = $this->getUserId();
        $limit = min((int) $request->query('limit', 50), 100);
        $offset = (int) $request->query('offset', 0);

        $conversations = $this->repository->getConversations($userId, $limit, $offset);

        return response()->json([
            'conversations' => array_map(fn($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'message_count' => $c->getMessageCount(),
                'last_message' => $c->getLastMessage()?->content,
                'created_at' => $c->createdAt?->format('Y-m-d H:i:s'),
                'updated_at' => $c->updatedAt?->format('Y-m-d H:i:s'),
            ], $conversations),
        ]);
    }

    /**
     * POST /botovis/conversations
     *
     * Body: { title?: string }
     */
    public function store(Request $request): JsonResponse
    {
        $authError = $this->checkAuth();
        if ($authError) return $authError;

        $request->validate([
            'title' => 'nullable|string|max:255',
        ]);

        $userId = $this->getUserId();
        $title = $request->input('title', 'Yeni Sohbet');

        $conversation = $this->repository->createConversation($userId, $title);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'created_at' => $conversation->createdAt?->format('Y-m-d H:i:s'),
            ],
        ], 201);
    }

    /**
     * GET /botovis/conversations/{id}
     */
    public function show(string $id): JsonResponse
    {
        $authError = $this->checkAuth();
        if ($authError) return $authError;

        $userId = $this->getUserId();

        // Check ownership
        if (!$this->repository->belongsToUser($id, $userId)) {
            return response()->json([
                'error' => 'Conversation not found',
            ], 404);
        }

        $conversation = $this->repository->getConversation($id, true);

        if (!$conversation) {
            return response()->json([
                'error' => 'Conversation not found',
            ], 404);
        }

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'messages' => array_map(fn($m) => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'content' => $m->content,
                    'intent' => $m->intent,
                    'action' => $m->action,
                    'success' => $m->success,
                    'created_at' => $m->createdAt?->format('Y-m-d H:i:s'),
                ], $conversation->messages),
                'created_at' => $conversation->createdAt?->format('Y-m-d H:i:s'),
                'updated_at' => $conversation->updatedAt?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * DELETE /botovis/conversations/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $authError = $this->checkAuth();
        if ($authError) return $authError;

        $userId = $this->getUserId();

        // Check ownership
        if (!$this->repository->belongsToUser($id, $userId)) {
            return response()->json([
                'error' => 'Conversation not found',
            ], 404);
        }

        $this->repository->deleteConversation($id);

        return response()->json([
            'message' => 'Conversation deleted',
        ]);
    }

    /**
     * PATCH /botovis/conversations/{id}/title
     *
     * Body: { title: string }
     */
    public function updateTitle(Request $request, string $id): JsonResponse
    {
        $authError = $this->checkAuth();
        if ($authError) return $authError;

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $userId = $this->getUserId();

        // Check ownership
        if (!$this->repository->belongsToUser($id, $userId)) {
            return response()->json([
                'error' => 'Conversation not found',
            ], 404);
        }

        $this->repository->updateTitle($id, $request->input('title'));

        return response()->json([
            'message' => 'Title updated',
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
     * Get current user's ID.
     */
    private function getUserId(): ?string
    {
        $guard = config('botovis.security.guard', 'web');
        $user = Auth::guard($guard)->user();

        return $user?->getAuthIdentifier() ? (string) $user->getAuthIdentifier() : null;
    }
}
