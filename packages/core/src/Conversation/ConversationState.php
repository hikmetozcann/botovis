<?php

declare(strict_types=1);

namespace Botovis\Core\Conversation;

use Botovis\Core\Intent\ResolvedIntent;

/**
 * Represents the current state of a Botovis conversation.
 *
 * Tracks chat history and pending actions awaiting confirmation.
 */
class ConversationState
{
    /** @var array<array{role: string, content: string}> */
    private array $history = [];

    /** The last intent that requires confirmation, waiting for user approval */
    private ?ResolvedIntent $pendingIntent = null;

    /**
     * Add a user message to history.
     */
    public function addUserMessage(string $message): void
    {
        $this->history[] = ['role' => 'user', 'content' => $message];
    }

    /**
     * Add an assistant message to history.
     */
    public function addAssistantMessage(string $message): void
    {
        $this->history[] = ['role' => 'assistant', 'content' => $message];
    }

    /**
     * Get the full conversation history (for LLM context).
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * Set a pending intent awaiting user confirmation.
     */
    public function setPendingIntent(ResolvedIntent $intent): void
    {
        $this->pendingIntent = $intent;
    }

    /**
     * Get the pending intent (if any).
     */
    public function getPendingIntent(): ?ResolvedIntent
    {
        return $this->pendingIntent;
    }

    /**
     * Clear the pending intent (after execution or rejection).
     */
    public function clearPendingIntent(): void
    {
        $this->pendingIntent = null;
    }

    /**
     * Check if there's a pending intent waiting for confirmation.
     */
    public function hasPendingIntent(): bool
    {
        return $this->pendingIntent !== null;
    }

    /**
     * Check if the user message is a confirmation.
     */
    public static function isConfirmation(string $message): bool
    {
        $message = mb_strtolower(trim($message));

        $confirmWords = [
            'evet', 'onay', 'onaylıyorum', 'onayla', 'tamam',
            'yap', 'uygula', 'çalıştır', 'sil', 'güncelle', 'ekle',
            'yes', 'ok', 'confirm', 'do it', 'go ahead',
            'e', 'olur', 'yap bunu', 'devam', 'devam et',
        ];

        foreach ($confirmWords as $word) {
            if ($message === $word || str_starts_with($message, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user message is a rejection.
     */
    public static function isRejection(string $message): bool
    {
        $message = mb_strtolower(trim($message));

        $rejectWords = [
            'hayır', 'iptal', 'vazgeç', 'istemiyorum', 'yapma',
            'no', 'cancel', 'abort', 'reject', 'stop',
        ];

        foreach ($rejectWords as $word) {
            if ($message === $word || str_starts_with($message, $word)) {
                return true;
            }
        }

        return false;
    }
}
