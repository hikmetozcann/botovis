<?php

declare(strict_types=1);

namespace Botovis\Core\DTO;

use DateTimeImmutable;

/**
 * Represents a conversation thread between a user and Botovis.
 */
final class Conversation
{
    /**
     * @param array<Message> $messages
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $userId,
        public readonly string $title,
        public readonly array $messages = [],
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $updatedAt = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Create from array (e.g., from database).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $messages = [];
        if (isset($data['messages']) && is_array($data['messages'])) {
            foreach ($data['messages'] as $msg) {
                $messages[] = $msg instanceof Message ? $msg : Message::fromArray($msg);
            }
        }

        return new self(
            id: $data['id'],
            userId: $data['user_id'] ?? null,
            title: $data['title'] ?? 'Yeni Sohbet',
            messages: $messages,
            createdAt: isset($data['created_at']) 
                ? (is_string($data['created_at']) ? new DateTimeImmutable($data['created_at']) : $data['created_at'])
                : null,
            updatedAt: isset($data['updated_at'])
                ? (is_string($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : $data['updated_at'])
                : null,
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * Convert to array for storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'title' => $this->title,
            'messages' => array_map(fn(Message $m) => $m->toArray(), $this->messages),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Get the last message in the conversation.
     */
    public function getLastMessage(): ?Message
    {
        if (empty($this->messages)) {
            return null;
        }
        return $this->messages[count($this->messages) - 1];
    }

    /**
     * Get message count.
     */
    public function getMessageCount(): int
    {
        return count($this->messages);
    }

    /**
     * Create a new instance with additional message.
     */
    public function withMessage(Message $message): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            title: $this->title,
            messages: [...$this->messages, $message],
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            metadata: $this->metadata,
        );
    }

    /**
     * Create a new instance with updated title.
     */
    public function withTitle(string $title): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            title: $title,
            messages: $this->messages,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            metadata: $this->metadata,
        );
    }
}
