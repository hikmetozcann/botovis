<?php

declare(strict_types=1);

namespace Botovis\Laravel\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Botovis\Core\Orchestrator;
use Botovis\Core\Contracts\SchemaDiscoveryInterface;
use Illuminate\Support\Str;

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
    public function __construct(
        private readonly Orchestrator $orchestrator,
    ) {}

    /**
     * POST /botovis/chat
     *
     * Body: { "message": "string", "conversation_id": "string?" }
     * Response: OrchestratorResponse JSON
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|string|max:64',
        ]);

        $message = $request->input('message');
        $conversationId = $request->input('conversation_id') ?? $this->generateConversationId($request);

        $response = $this->orchestrator->handle($conversationId, $message);

        return response()->json([
            'conversation_id' => $conversationId,
            ...$response->toArray(),
        ]);
    }

    /**
     * POST /botovis/confirm
     *
     * Body: { "conversation_id": "string" }
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|string|max:64',
        ]);

        $conversationId = $request->input('conversation_id');
        $response = $this->orchestrator->confirm($conversationId);

        return response()->json([
            'conversation_id' => $conversationId,
            ...$response->toArray(),
        ]);
    }

    /**
     * POST /botovis/reject
     *
     * Body: { "conversation_id": "string" }
     */
    public function reject(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|string|max:64',
        ]);

        $conversationId = $request->input('conversation_id');
        $response = $this->orchestrator->reject($conversationId);

        return response()->json([
            'conversation_id' => $conversationId,
            ...$response->toArray(),
        ]);
    }

    /**
     * POST /botovis/reset
     *
     * Body: { "conversation_id": "string" }
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|string|max:64',
        ]);

        $conversationId = $request->input('conversation_id');
        $this->orchestrator->reset($conversationId);

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
     */
    public function schema(SchemaDiscoveryInterface $discovery): JsonResponse
    {
        $schema = $discovery->discover();

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

        return response()->json([
            'tables' => $tables,
        ]);
    }

    /**
     * GET /botovis/status
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'version' => '0.1.0',
        ]);
    }

    /**
     * Generate a deterministic conversation ID per user session.
     */
    private function generateConversationId(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier() ?? 'guest';
        return 'botovis_' . $userId . '_' . Str::random(8);
    }
}
