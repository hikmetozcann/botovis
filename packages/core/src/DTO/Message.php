<?php

declare(strict_types=1);

namespace Botovis\Core\DTO;

use DateTimeImmutable;
use Botovis\Core\Enums\ActionType;

/**
 * Represents a single message in a conversation.
 */
final class Message
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_SYSTEM = 'system';

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $conversationId,
        public readonly string $role,
        public readonly string $content,
        public readonly ?string $intent = null,
        public readonly ?string $action = null,
        public readonly ?string $table = null,
        public readonly array $parameters = [],
        public readonly ?bool $success = null,
        public readonly ?int $executionTimeMs = null,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a user message.
     */
    public static function user(
        string $id,
        string $conversationId,
        string $content,
        ?DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            id: $id,
            conversationId: $conversationId,
            role: self::ROLE_USER,
            content: $content,
            createdAt: $createdAt ?? new DateTimeImmutable(),
        );
    }

    /**
     * Create an assistant message with execution details.
     */
    public static function assistant(
        string $id,
        string $conversationId,
        string $content,
        ?string $intent = null,
        ?ActionType $action = null,
        ?string $table = null,
        array $parameters = [],
        ?bool $success = null,
        ?int $executionTimeMs = null,
        ?DateTimeImmutable $createdAt = null,
        array $metadata = [],
    ): self {
        return new self(
            id: $id,
            conversationId: $conversationId,
            role: self::ROLE_ASSISTANT,
            content: $content,
            intent: $intent,
            action: $action?->value,
            table: $table,
            parameters: $parameters,
            success: $success,
            executionTimeMs: $executionTimeMs,
            createdAt: $createdAt ?? new DateTimeImmutable(),
            metadata: $metadata,
        );
    }

    /**
     * Create from array (e.g., from database).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            conversationId: $data['conversation_id'],
            role: $data['role'],
            content: $data['content'],
            intent: $data['intent'] ?? null,
            action: $data['action'] ?? null,
            table: $data['table'] ?? null,
            parameters: $data['parameters'] ?? [],
            success: $data['success'] ?? null,
            executionTimeMs: $data['execution_time_ms'] ?? null,
            createdAt: isset($data['created_at'])
                ? (is_string($data['created_at']) ? new DateTimeImmutable($data['created_at']) : $data['created_at'])
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
            'conversation_id' => $this->conversationId,
            'role' => $this->role,
            'content' => $this->content,
            'intent' => $this->intent,
            'action' => $this->action,
            'table' => $this->table,
            'parameters' => $this->parameters,
            'success' => $this->success,
            'execution_time_ms' => $this->executionTimeMs,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Check if this is a user message.
     */
    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    /**
     * Check if this is an assistant message.
     */
    public function isAssistant(): bool
    {
        return $this->role === self::ROLE_ASSISTANT;
    }

    /**
     * Check if this message was successful (for assistant messages).
     */
    public function wasSuccessful(): bool
    {
        return $this->success === true;
    }
}
