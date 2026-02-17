<?php

declare(strict_types=1);

namespace Botovis\Telegram;

/**
 * Telegram Bot API client.
 *
 * Minimal wrapper for the Telegram Bot API methods used by Botovis.
 * No external HTTP library dependency — uses cURL directly.
 */
class TelegramApi
{
    private string $baseUrl;

    public function __construct(
        private readonly string $botToken,
    ) {
        $this->baseUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Send a text message.
     *
     * @param string|int $chatId
     * @param string $text
     * @param string $parseMode 'MarkdownV2' | 'HTML' | ''
     * @param array|null $replyMarkup Inline keyboard or other markup
     * @return array Telegram API response
     */
    public function sendMessage(
        string|int $chatId,
        string $text,
        string $parseMode = 'MarkdownV2',
        ?array $replyMarkup = null,
    ): array {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }

        if ($replyMarkup !== null) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->request('sendMessage', $params);
    }

    /**
     * Send a plain text message (no parse mode — safe for any content).
     */
    public function sendPlainMessage(string|int $chatId, string $text, ?array $replyMarkup = null): array
    {
        return $this->sendMessage($chatId, $text, '', $replyMarkup);
    }

    /**
     * Send "typing..." indicator.
     */
    public function sendTypingAction(string|int $chatId): array
    {
        return $this->request('sendChatAction', [
            'chat_id' => $chatId,
            'action' => 'typing',
        ]);
    }

    /**
     * Answer a callback query (inline keyboard button press).
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): array
    {
        return $this->request('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]);
    }

    /**
     * Edit an existing message's text.
     */
    public function editMessageText(
        string|int $chatId,
        int $messageId,
        string $text,
        string $parseMode = 'MarkdownV2',
        ?array $replyMarkup = null,
    ): array {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ];

        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }

        if ($replyMarkup !== null) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->request('editMessageText', $params);
    }

    /**
     * Set webhook URL for receiving updates.
     */
    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        $params = ['url' => $url];

        if ($secretToken) {
            $params['secret_token'] = $secretToken;
        }

        return $this->request('setWebhook', $params);
    }

    /**
     * Remove webhook.
     */
    public function deleteWebhook(): array
    {
        return $this->request('deleteWebhook');
    }

    /**
     * Get current webhook info.
     */
    public function getWebhookInfo(): array
    {
        return $this->request('getWebhookInfo');
    }

    /**
     * Get bot info.
     */
    public function getMe(): array
    {
        return $this->request('getMe');
    }

    /**
     * Set bot commands menu.
     */
    public function setMyCommands(array $commands): array
    {
        return $this->request('setMyCommands', [
            'commands' => json_encode($commands),
        ]);
    }

    /**
     * Build an inline keyboard with confirm/reject buttons.
     */
    public static function confirmationKeyboard(string $confirmData, string $rejectData): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Onayla', 'callback_data' => $confirmData],
                    ['text' => '❌ İptal', 'callback_data' => $rejectData],
                ],
            ],
        ];
    }

    /**
     * Make a request to the Telegram Bot API.
     */
    private function request(string $method, array $params = []): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => "{$this->baseUrl}/{$method}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Botovis Telegram API error: {$error}");
        }

        $decoded = json_decode($response, true);

        if (!($decoded['ok'] ?? false)) {
            $description = $decoded['description'] ?? "HTTP {$httpCode}";
            \Log::warning('[Botovis Telegram] API error', [
                'method' => $method,
                'description' => $description,
                'http_code' => $httpCode,
            ]);
        }

        return $decoded ?? [];
    }
}
