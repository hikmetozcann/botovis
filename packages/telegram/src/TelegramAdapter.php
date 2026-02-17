<?php

declare(strict_types=1);

namespace Botovis\Telegram;

use Botovis\Core\Agent\AgentOrchestrator;
use Botovis\Core\Agent\AgentResponse;
use Botovis\Core\Agent\StreamingEvent;
use Botovis\Core\DTO\SecurityContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Telegram Channel Adapter.
 *
 * Bridges Telegram messages to Botovis AgentOrchestrator.
 * Handles: user linking, message processing, confirmation flow,
 * bot commands, and response formatting.
 */
class TelegramAdapter
{
    public function __construct(
        private readonly TelegramApi $api,
        private readonly AgentOrchestrator $orchestrator,
    ) {}

    /**
     * Process an incoming Telegram update.
     */
    public function handleUpdate(array $update): void
    {
        // Handle callback queries (inline keyboard button presses)
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
            return;
        }

        // Handle text messages
        $message = $update['message'] ?? null;
        if (!$message || !isset($message['text'])) {
            return;
        }

        $chatId = (string) $message['chat']['id'];
        $text = trim($message['text']);
        $telegramUserId = (string) $message['from']['id'];

        // Handle bot commands
        if (str_starts_with($text, '/')) {
            $this->handleCommand($chatId, $text, $telegramUserId);
            return;
        }

        // Find linked Laravel user
        $user = $this->findUserByChatId($chatId);

        if (!$user) {
            $guestMessage = config('botovis-telegram.guest_message', 'Please link your Telegram account first.');
            $this->api->sendPlainMessage($chatId, $guestMessage);
            return;
        }

        // Process message through Botovis
        $this->processMessage($chatId, $text, $user);
    }

    /**
     * Handle bot commands (/start, /connect, /help, /tables, /reset).
     */
    private function handleCommand(string $chatId, string $text, string $telegramUserId): void
    {
        $parts = explode(' ', $text, 2);
        $command = strtolower($parts[0]);
        $argument = $parts[1] ?? '';

        match ($command) {
            '/start' => $this->commandStart($chatId),
            '/connect' => $this->commandConnect($chatId, trim($argument)),
            '/disconnect' => $this->commandDisconnect($chatId),
            '/help' => $this->commandHelp($chatId),
            '/tables' => $this->commandTables($chatId),
            '/reset' => $this->commandReset($chatId),
            '/status' => $this->commandStatus($chatId),
            default => $this->api->sendPlainMessage($chatId, "Bilinmeyen komut. /help yazarak kullanılabilir komutları görebilirsiniz."),
        };
    }

    /**
     * /start — Welcome message.
     */
    private function commandStart(string $chatId): void
    {
        $user = $this->findUserByChatId($chatId);

        if ($user) {
            $name = $user->name ?? $user->email ?? 'Kullanıcı';
            $this->api->sendPlainMessage($chatId, "👋 Merhaba {$name}! Botovis'e soru sormaya başlayabilirsiniz.\n\n/help — Komutları göster\n/tables — Erişilebilir tabloları listele");
        } else {
            $this->api->sendPlainMessage($chatId, "👋 Botovis Telegram Bot'a hoş geldiniz!\n\nBu botu kullanmak için önce hesabınızı bağlamanız gerekiyor:\n\n1. Uygulamanızın panelinè gidin\n2. 'Telegram Bağla' bölümünden bir bağlantı kodu alın\n3. Buraya /connect KODUNUZ yazın\n\nÖrnek: /connect 482951");
        }
    }

    /**
     * /connect <code> — Link Telegram account to Laravel user.
     */
    private function commandConnect(string $chatId, string $code): void
    {
        if (empty($code)) {
            $this->api->sendPlainMessage($chatId, "❌ Kullanım: /connect KODUNUZ\n\nÖrnek: /connect 482951\n\nKodu uygulamanızın panelinden alabilirsiniz.");
            return;
        }

        // Check if already linked
        $existing = $this->findUserByChatId($chatId);
        if ($existing) {
            $this->api->sendPlainMessage($chatId, "✅ Hesabınız zaten bağlı ({$existing->email}). Önce /disconnect ile bağlantıyı kaldırın.");
            return;
        }

        // Look up connect code in cache
        $cacheKey = "botovis_telegram_connect:{$code}";
        $userId = Cache::get($cacheKey);

        if (!$userId) {
            $this->api->sendPlainMessage($chatId, "❌ Geçersiz veya süresi dolmuş kod. Lütfen panelden yeni bir kod alın.");
            return;
        }

        // Link the user
        $userModel = $this->getUserModel();
        $user = $userModel::find($userId);

        if (!$user) {
            $this->api->sendPlainMessage($chatId, "❌ Kullanıcı bulunamadı.");
            return;
        }

        $user->telegram_chat_id = $chatId;
        $user->save();

        // Remove used code
        Cache::forget($cacheKey);

        $name = $user->name ?? $user->email;
        $this->api->sendPlainMessage($chatId, "✅ Hesap başarıyla bağlandı!\n\n👤 {$name}\n📧 {$user->email}\n\nArtık Botovis'e soru sormaya başlayabilirsiniz.");

        Log::info('[Botovis Telegram] User linked', [
            'user_id' => $userId,
            'chat_id' => $chatId,
        ]);
    }

    /**
     * /disconnect — Unlink Telegram account.
     */
    private function commandDisconnect(string $chatId): void
    {
        $user = $this->findUserByChatId($chatId);

        if (!$user) {
            $this->api->sendPlainMessage($chatId, "❌ Bu Telegram hesabına bağlı bir kullanıcı yok.");
            return;
        }

        $user->telegram_chat_id = null;
        $user->save();

        $this->api->sendPlainMessage($chatId, "✅ Telegram bağlantısı kaldırıldı.");
    }

    /**
     * /help — Show available commands.
     */
    private function commandHelp(string $chatId): void
    {
        $help = "🤖 *Botovis Telegram Bot*\n\n"
            . "*Komutlar:*\n"
            . "/connect KODUNUZ \\- Hesabınızı bağlayın\n"
            . "/disconnect \\- Bağlantıyı kaldırın\n"
            . "/tables \\- Erişilebilir tabloları listeleyin\n"
            . "/reset \\- Konuşmayı sıfırlayın\n"
            . "/status \\- Bağlantı durumunu görün\n"
            . "/help \\- Bu mesajı görün\n\n"
            . "*Kullanım:*\n"
            . "Doğal dilde soru yazmanız yeterli\\.\n\n"
            . "Örnekler:\n"
            . "• Kaç aktif müşteri var\\?\n"
            . "• Bu ayın en çok satan 5 ürünü\n"
            . "• iPhone fiyatını 52999'a güncelle";

        $this->api->sendMessage($chatId, $help, 'MarkdownV2');
    }

    /**
     * /tables — List accessible tables.
     */
    private function commandTables(string $chatId): void
    {
        $user = $this->findUserByChatId($chatId);

        if (!$user) {
            $this->api->sendPlainMessage($chatId, "❌ Önce hesabınızı bağlayın. /connect KODUNUZ");
            return;
        }

        $this->setSecurityContextForUser($user);
        $context = $this->orchestrator->getSecurityContext();
        $tables = $context->getAccessibleTables();

        if (empty($tables) || $tables === ['*']) {
            $models = array_keys(config('botovis.models', []));
            $tables = array_map(fn($m) => class_basename($m), $models);
        }

        if (empty($tables)) {
            $this->api->sendPlainMessage($chatId, "📋 Erişilebilir tablo bulunamadı.");
            return;
        }

        $list = array_map(fn($t) => "• {$t}", $tables);
        $this->api->sendPlainMessage($chatId, "📋 Erişilebilir Tablolar:\n\n" . implode("\n", $list));
    }

    /**
     * /reset — Reset conversation.
     */
    private function commandReset(string $chatId): void
    {
        $conversationId = $this->getConversationId($chatId);
        $this->orchestrator->reset($conversationId);
        $this->api->sendPlainMessage($chatId, "🔄 Konuşma sıfırlandı. Yeni bir soru sorabilirsiniz.");
    }

    /**
     * /status — Show connection status.
     */
    private function commandStatus(string $chatId): void
    {
        $user = $this->findUserByChatId($chatId);

        if ($user) {
            $name = $user->name ?? 'Bilinmiyor';
            $this->api->sendPlainMessage($chatId, "✅ Bağlı\n\n👤 {$name}\n📧 {$user->email}\n🆔 Chat ID: {$chatId}");
        } else {
            $this->api->sendPlainMessage($chatId, "❌ Bağlı değil\n\n🆔 Chat ID: {$chatId}\n\nHesabınızı bağlamak için /connect KODUNUZ yazın.");
        }
    }

    /**
     * Process a regular message through the agent.
     */
    private function processMessage(string $chatId, string $text, $user): void
    {
        $conversationId = $this->getConversationId($chatId);

        // Set security context for this user
        $this->setSecurityContextForUser($user);

        // Send initial typing indicator
        $this->api->sendTypingAction($chatId);
        $lastTyping = time();

        try {
            // Use streaming to get step-by-step events
            // This lets us refresh the typing indicator between steps
            $stream = $this->orchestrator->stream($conversationId, $text);

            $finalMessage = null;
            $finalSteps = [];
            $confirmationData = null;
            $errorMessage = null;

            foreach ($stream as $event) {
                // Refresh typing indicator every 4 seconds
                if (time() - $lastTyping >= 4) {
                    $this->api->sendTypingAction($chatId);
                    $lastTyping = time();
                }

                match ($event->type) {
                    StreamingEvent::TYPE_STEP => $finalSteps[] = $event->data,
                    StreamingEvent::TYPE_MESSAGE => $finalMessage = $event->data['content'] ?? '',
                    StreamingEvent::TYPE_CONFIRMATION => $confirmationData = $event->data,
                    StreamingEvent::TYPE_ERROR => $errorMessage = $event->data['message'] ?? 'Bilinmeyen hata',
                    default => null,
                };
            }

            // Show reasoning steps if enabled
            if (config('botovis-telegram.show_steps', false) && !empty($finalSteps)) {
                foreach ($finalSteps as $step) {
                    $action = $step['action'] ?? '';
                    $thought = $step['thought'] ?? '';
                    if ($action) {
                        $stepText = TelegramFormatter::formatStep($thought, $action, $step['action_params'] ?? []);
                        if ($stepText) {
                            try {
                                $this->api->sendMessage($chatId, $stepText, 'HTML');
                            } catch (\Throwable) {
                                $this->api->sendPlainMessage($chatId, "🔧 {$action}");
                            }
                        }
                    }
                }
            }

            // Send final response
            if ($confirmationData) {
                $response = AgentResponse::confirmation(
                    $confirmationData['description'] ?? '',
                    [
                        'action' => $confirmationData['action'] ?? '',
                        'params' => $confirmationData['params'] ?? [],
                    ],
                );
                $this->sendConfirmation($chatId, $response, $conversationId);
            } elseif ($errorMessage) {
                $this->api->sendPlainMessage($chatId, "❌ " . $errorMessage);
            } elseif ($finalMessage) {
                $this->sendFormattedMessage($chatId, $finalMessage);
            }

        } catch (\Throwable $e) {
            Log::error('[Botovis Telegram] Error processing message', [
                'chat_id' => $chatId,
                'message' => $text,
                'error' => $e->getMessage(),
            ]);
            $this->api->sendPlainMessage($chatId, "❌ Bir hata oluştu: " . $e->getMessage());
        }
    }

    /**
     * Send a formatted text message, with HTML fallback to plain.
     */
    private function sendFormattedMessage(string $chatId, string $message): void
    {
        $formatted = TelegramFormatter::format($message);

        try {
            $this->api->sendMessage($chatId, $formatted['text'], $formatted['parse_mode']);
        } catch (\Throwable) {
            // Fallback to plain text if HTML parsing fails
            $plain = TelegramFormatter::stripMarkdown($message);
            $this->api->sendPlainMessage($chatId, $plain);
        }
    }

    /**
     * Send a confirmation prompt with inline keyboard.
     */
    private function sendConfirmation(string $chatId, AgentResponse $response, string $conversationId): void
    {
        $text = TelegramFormatter::formatConfirmation($response->message, $response->pendingAction);

        $keyboard = TelegramApi::confirmationKeyboard(
            "confirm:{$conversationId}",
            "reject:{$conversationId}",
        );

        try {
            $this->api->sendMessage($chatId, $text, 'HTML', $keyboard);
        } catch (\Throwable) {
            // Fallback
            $plain = "⚠️ Yazma İşlemi\n\n" . $response->message;
            $this->api->sendPlainMessage($chatId, $plain, $keyboard);
        }
    }

    /**
     * Handle inline keyboard button presses (confirm/reject).
     */
    private function handleCallbackQuery(array $callbackQuery): void
    {
        $data = $callbackQuery['data'] ?? '';
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? '');
        $messageId = $callbackQuery['message']['message_id'] ?? 0;
        $callbackQueryId = $callbackQuery['id'];

        if (!$chatId || !$data) {
            return;
        }

        // Parse callback data: "confirm:conv_id" or "reject:conv_id"
        $parts = explode(':', $data, 2);
        $action = $parts[0] ?? '';
        $conversationId = $parts[1] ?? '';

        if (!in_array($action, ['confirm', 'reject']) || !$conversationId) {
            $this->api->answerCallbackQuery($callbackQueryId, 'Geçersiz işlem.');
            return;
        }

        // Find and set user context
        $user = $this->findUserByChatId($chatId);
        if (!$user) {
            $this->api->answerCallbackQuery($callbackQueryId, 'Kullanıcı bulunamadı.');
            return;
        }
        $this->setSecurityContextForUser($user);

        // Send typing
        $this->api->sendTypingAction($chatId);

        try {
            if ($action === 'confirm') {
                $this->api->answerCallbackQuery($callbackQueryId, '✅ Onaylandı, işleniyor...');

                $response = $this->orchestrator->confirm($conversationId);

                // Edit the original message to remove buttons
                try {
                    $this->api->editMessageText(
                        $chatId,
                        $messageId,
                        '✅ İşlem onaylandı.',
                        'HTML',
                    );
                } catch (\Throwable) {
                    // Editing might fail if message is too old
                }

                // Send result
                $this->sendFormattedMessage($chatId, $response->message);
            } else {
                $this->api->answerCallbackQuery($callbackQueryId, '❌ İptal edildi.');

                $response = $this->orchestrator->reject($conversationId);

                try {
                    $this->api->editMessageText(
                        $chatId,
                        $messageId,
                        '❌ İşlem iptal edildi.',
                        'HTML',
                    );
                } catch (\Throwable) {
                    // Editing might fail
                }
            }
        } catch (\Throwable $e) {
            Log::error('[Botovis Telegram] Callback error', [
                'chat_id' => $chatId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
            $this->api->sendPlainMessage($chatId, "❌ Hata: " . $e->getMessage());
        }
    }

    // ────────────────────────────────────────────
    //  Helper Methods
    // ────────────────────────────────────────────

    /**
     * Generate a connect code for a user.
     */
    public static function generateConnectCode(int|string $userId): string
    {
        $code = (string) random_int(100000, 999999);
        $ttl = config('botovis-telegram.connect_code_ttl', 300);

        Cache::put("botovis_telegram_connect:{$code}", $userId, $ttl);

        return $code;
    }

    /**
     * Find a Laravel user by telegram_chat_id.
     */
    private function findUserByChatId(string $chatId): ?object
    {
        $userModel = $this->getUserModel();
        return $userModel::where('telegram_chat_id', $chatId)->first();
    }

    /**
     * Get the User model class.
     */
    private function getUserModel(): string
    {
        return config('auth.providers.users.model', 'App\\Models\\User');
    }

    /**
     * Build a conversation ID from chat_id.
     */
    private function getConversationId(string $chatId): string
    {
        return "telegram_{$chatId}";
    }

    /**
     * Set the security context on the orchestrator for a given user.
     */
    private function setSecurityContextForUser(object $user): void
    {
        // Use BotovisAuthorizer if available, otherwise build a basic context
        try {
            $authorizer = app(\Botovis\Laravel\Security\BotovisAuthorizer::class);

            // We need to set the auth user for this request
            $guard = config('botovis.security.guard', 'web');
            \Illuminate\Support\Facades\Auth::guard($guard)->setUser($user);

            $this->orchestrator->setAuthorizer($authorizer);
        } catch (\Throwable) {
            // Fallback: build context manually
            $context = new SecurityContext(
                userId: (string) $user->getAuthIdentifier(),
                userRole: $user->role ?? 'user',
                allowedTables: ['*'],
                permissions: ['*' => ['*']],
                metadata: [
                    'user_name' => $user->name ?? $user->email ?? 'User',
                ],
            );
            $this->orchestrator->setSecurityContext($context);
        }
    }
}
