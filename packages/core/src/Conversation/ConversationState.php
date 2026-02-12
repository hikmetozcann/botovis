<?php

declare(strict_types=1);

namespace Botovis\Core\Conversation;

use Botovis\Core\Agent\AgentState;
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

    /** The agent state when waiting for confirmation (new agent system) */
    private ?AgentState $pendingAgentState = null;

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
     * Check if the user message is a rejection (possibly with trailing text).
     *
     * Matches: "hayır", "hayır ama ...", "iptal", "no way" etc.
     */
    public static function isRejection(string $message): bool
    {
        $message = mb_strtolower(trim($message));

        foreach (self::rejectionWords() as $word) {
            // Exact match or word followed by space/punctuation ("hayır ama ...")
            if ($message === $word || preg_match('/^' . preg_quote($word, '/') . '[\s,\.!]+/u', $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the rejection message has additional content after the rejection word.
     * Returns the remainder text, or null if it's a pure rejection.
     *
     * "hayır" → null
     * "hayır yusufun numarası ..." → "yusufun numarası ..."
     */
    public static function extractAfterRejection(string $message): ?string
    {
        $lower = mb_strtolower(trim($message));

        foreach (self::rejectionWords() as $word) {
            if (preg_match('/^' . preg_quote($word, '/') . '[\s,\.!]+(.+)$/su', $lower, $m)) {
                $remainder = trim($m[1]);
                // Return original casing: skip the rejection word + separator
                $original = trim($message);
                $offset = mb_strlen($word);
                $rest = trim(mb_substr($original, $offset));
                // Strip leading punctuation/spaces
                $rest = ltrim($rest, ' ,.');
                return $rest !== '' ? $rest : null;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private static function rejectionWords(): array
    {
        return [
            'hayır', 'iptal', 'vazgeç', 'istemiyorum', 'yapma',
            'no', 'cancel', 'abort', 'reject', 'stop',
        ];
    }

    // ──────────────────────────────────────────────
    //  Agent State (new agent-based system)
    // ──────────────────────────────────────────────

    /**
     * Set a pending agent state awaiting user confirmation.
     */
    public function setPendingAgentState(AgentState $state): void
    {
        $this->pendingAgentState = $state;
    }

    /**
     * Get the pending agent state (if any).
     */
    public function getPendingAgentState(): ?AgentState
    {
        return $this->pendingAgentState;
    }

    /**
     * Clear the pending agent state.
     */
    public function clearPendingAgentState(): void
    {
        $this->pendingAgentState = null;
    }

    /**
     * Check if there's a pending agent state waiting for confirmation.
     */
    public function hasPendingAgentState(): bool
    {
        return $this->pendingAgentState !== null;
    }
}
