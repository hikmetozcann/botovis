<?php

declare(strict_types=1);

namespace Botovis\Telegram\Http;

use Botovis\Telegram\TelegramAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Webhook controller for incoming Telegram updates.
 *
 * Telegram sends POST requests to this endpoint when a user
 * sends a message or presses an inline button.
 */
class WebhookController extends Controller
{
    /**
     * Handle incoming Telegram webhook.
     */
    public function handle(Request $request, TelegramAdapter $adapter): JsonResponse
    {
        // Verify webhook is enabled
        if (!config('botovis-telegram.enabled', false)) {
            return response()->json(['error' => 'Telegram integration is disabled'], 404);
        }

        // Verify secret token (if configured)
        $secret = config('botovis-telegram.webhook_secret');
        if ($secret) {
            $headerSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if ($headerSecret !== $secret) {
                Log::warning('[Botovis Telegram] Invalid webhook secret', [
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        $update = $request->all();

        if (empty($update)) {
            return response()->json(['error' => 'Empty update'], 400);
        }

        Log::debug('[Botovis Telegram] Incoming update', [
            'update_id' => $update['update_id'] ?? null,
            'type' => isset($update['callback_query']) ? 'callback_query' : 'message',
        ]);

        try {
            $adapter->handleUpdate($update);
        } catch (\Throwable $e) {
            Log::error('[Botovis Telegram] Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Always return 200 to Telegram (otherwise it retries)
        return response()->json(['ok' => true]);
    }

    /**
     * Generate a connect code for the authenticated user.
     *
     * Called from the app's panel (requires auth middleware).
     */
    public function generateCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Check if already linked
        if ($user->telegram_chat_id) {
            return response()->json([
                'linked' => true,
                'telegram_chat_id' => $user->telegram_chat_id,
                'message' => 'Telegram hesabınız zaten bağlı.',
            ]);
        }

        $code = TelegramAdapter::generateConnectCode($user->id);
        $ttl = config('botovis-telegram.connect_code_ttl', 300);

        return response()->json([
            'linked' => false,
            'code' => $code,
            'expires_in' => $ttl,
            'message' => "Telegram botta /connect {$code} yazın. Kod {$ttl} saniye geçerlidir.",
        ]);
    }

    /**
     * Disconnect Telegram from the authenticated user.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!$user->telegram_chat_id) {
            return response()->json([
                'message' => 'Telegram hesabı zaten bağlı değil.',
            ]);
        }

        $user->telegram_chat_id = null;
        $user->save();

        return response()->json([
            'message' => 'Telegram bağlantısı kaldırıldı.',
        ]);
    }
}
