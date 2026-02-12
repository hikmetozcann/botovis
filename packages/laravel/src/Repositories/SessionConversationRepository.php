<?php

declare(strict_types=1);

namespace Botovis\Laravel\Repositories;

use Botovis\Core\Contracts\ConversationRepositoryInterface;
use Botovis\Core\DTO\Conversation;
use Botovis\Core\DTO\Message;
use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Session-based implementation of conversation repository.
 * Conversations are stored in the session and cleared when session ends.
 */
class SessionConversationRepository implements ConversationRepositoryInterface
{
    private const SESSION_KEY = 'botovis_conversations';

    /**
     * @inheritDoc
     */
    public function getConversations(?string $userId, int $limit = 50, int $offset = 0): array
    {
        $conversations = $this->getAllFromSession();

        // Filter by user_id
        $filtered = array_filter($conversations, fn($c) => $c['user_id'] === $userId);

        // Sort by updated_at desc
        usort($filtered, fn($a, $b) => ($b['updated_at'] ?? '') <=> ($a['updated_at'] ?? ''));

        // Paginate
        $sliced = array_slice($filtered, $offset, $limit);

        return array_map(fn($data) => Conversation::fromArray($data), $sliced);
    }

    /**
     * @inheritDoc
     */
    public function getConversation(string $id, bool $withMessages = true): ?Conversation
    {
        $conversations = $this->getAllFromSession();

        if (!isset($conversations[$id])) {
            return null;
        }

        $data = $conversations[$id];

        if (!$withMessages) {
            $data['messages'] = [];
        }

        return Conversation::fromArray($data);
    }

    /**
     * @inheritDoc
     */
    public function createConversation(?string $userId, string $title = 'Yeni Sohbet', array $metadata = []): Conversation
    {
        $id = Str::uuid()->toString();
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $data = [
            'id' => $id,
            'user_id' => $userId,
            'title' => $title,
            'messages' => [],
            'metadata' => $metadata,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $conversations = $this->getAllFromSession();
        $conversations[$id] = $data;
        $this->saveToSession($conversations);

        return Conversation::fromArray($data);
    }

    /**
     * @inheritDoc
     */
    public function updateTitle(string $conversationId, string $title): bool
    {
        $conversations = $this->getAllFromSession();

        if (!isset($conversations[$conversationId])) {
            return false;
        }

        $conversations[$conversationId]['title'] = $title;
        $conversations[$conversationId]['updated_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->saveToSession($conversations);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteConversation(string $conversationId): bool
    {
        $conversations = $this->getAllFromSession();

        if (!isset($conversations[$conversationId])) {
            return false;
        }

        unset($conversations[$conversationId]);
        $this->saveToSession($conversations);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMessages(string $conversationId, int $limit = 100, int $offset = 0): array
    {
        $conversations = $this->getAllFromSession();

        if (!isset($conversations[$conversationId])) {
            return [];
        }

        $messages = $conversations[$conversationId]['messages'] ?? [];
        $sliced = array_slice($messages, $offset, $limit);

        return array_map(fn($data) => Message::fromArray($data), $sliced);
    }

    /**
     * @inheritDoc
     */
    public function addMessage(Message $message): Message
    {
        $conversations = $this->getAllFromSession();

        if (!isset($conversations[$message->conversationId])) {
            return $message;
        }

        $messageData = $message->toArray();
        if (empty($messageData['id'])) {
            $messageData['id'] = Str::uuid()->toString();
        }
        if (empty($messageData['created_at'])) {
            $messageData['created_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        }

        $conversations[$message->conversationId]['messages'][] = $messageData;
        $conversations[$message->conversationId]['updated_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->saveToSession($conversations);

        return Message::fromArray($messageData);
    }

    /**
     * @inheritDoc
     */
    public function getLastMessages(string $conversationId, int $count = 10): array
    {
        $conversations = $this->getAllFromSession();

        if (!isset($conversations[$conversationId])) {
            return [];
        }

        $messages = $conversations[$conversationId]['messages'] ?? [];
        $lastMessages = array_slice($messages, -$count);

        return array_map(fn($data) => Message::fromArray($data), $lastMessages);
    }

    /**
     * @inheritDoc
     */
    public function belongsToUser(string $conversationId, ?string $userId): bool
    {
        $conversations = $this->getAllFromSession();

        if (!isset($conversations[$conversationId])) {
            return false;
        }

        return $conversations[$conversationId]['user_id'] === $userId;
    }

    /**
     * Get all conversations from session.
     *
     * @return array<string, array>
     */
    private function getAllFromSession(): array
    {
        return session(self::SESSION_KEY, []);
    }

    /**
     * Save conversations to session.
     *
     * @param array<string, array> $conversations
     */
    private function saveToSession(array $conversations): void
    {
        session([self::SESSION_KEY => $conversations]);
    }
}
