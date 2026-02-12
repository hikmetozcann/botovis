<?php

declare(strict_types=1);

namespace Botovis\Core\Contracts;

use Botovis\Core\Conversation\ConversationState;

/**
 * Manages conversation state persistence across HTTP requests.
 *
 * Each framework implements this using its own session/cache mechanism:
 * - Laravel: Cache (Redis/File/DB)
 * - Node: Redis/Memory
 * - .NET: IDistributedCache
 */
interface ConversationManagerInterface
{
    /**
     * Get or create a conversation by its ID.
     */
    public function get(string $conversationId): ConversationState;

    /**
     * Persist the conversation state.
     */
    public function save(string $conversationId, ConversationState $state): void;

    /**
     * Delete a conversation.
     */
    public function delete(string $conversationId): void;

    /**
     * Check if a conversation exists.
     */
    public function exists(string $conversationId): bool;
}
