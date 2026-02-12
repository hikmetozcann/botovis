<?php

declare(strict_types=1);

namespace Botovis\Core\Contracts;

use Botovis\Core\DTO\Conversation;
use Botovis\Core\DTO\Message;

/**
 * Contract for conversation persistence.
 */
interface ConversationRepositoryInterface
{
    /**
     * Get all conversations for a user.
     *
     * @param string|null $userId
     * @param int $limit
     * @param int $offset
     * @return array<Conversation>
     */
    public function getConversations(?string $userId, int $limit = 50, int $offset = 0): array;

    /**
     * Get a single conversation by ID.
     *
     * @param string $id
     * @param bool $withMessages Whether to load messages
     * @return Conversation|null
     */
    public function getConversation(string $id, bool $withMessages = true): ?Conversation;

    /**
     * Create a new conversation.
     *
     * @param string|null $userId
     * @param string $title
     * @param array<string, mixed> $metadata
     * @return Conversation
     */
    public function createConversation(?string $userId, string $title = 'Yeni Sohbet', array $metadata = []): Conversation;

    /**
     * Update conversation title.
     *
     * @param string $conversationId
     * @param string $title
     * @return bool
     */
    public function updateTitle(string $conversationId, string $title): bool;

    /**
     * Delete a conversation and all its messages.
     *
     * @param string $conversationId
     * @return bool
     */
    public function deleteConversation(string $conversationId): bool;

    /**
     * Get messages for a conversation.
     *
     * @param string $conversationId
     * @param int $limit
     * @param int $offset
     * @return array<Message>
     */
    public function getMessages(string $conversationId, int $limit = 100, int $offset = 0): array;

    /**
     * Add a message to a conversation.
     *
     * @param Message $message
     * @return Message The saved message with ID
     */
    public function addMessage(Message $message): Message;

    /**
     * Get the last N messages for context.
     *
     * @param string $conversationId
     * @param int $count
     * @return array<Message>
     */
    public function getLastMessages(string $conversationId, int $count = 10): array;

    /**
     * Check if a conversation belongs to a user.
     *
     * @param string $conversationId
     * @param string|null $userId
     * @return bool
     */
    public function belongsToUser(string $conversationId, ?string $userId): bool;
}
