<?php

declare(strict_types=1);

namespace Botovis\Laravel\Repositories;

use Botovis\Core\Contracts\ConversationRepositoryInterface;
use Botovis\Core\DTO\Conversation;
use Botovis\Core\DTO\Message;
use Botovis\Laravel\Models\BotovisConversation;
use Botovis\Laravel\Models\BotovisMessage;
use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Eloquent implementation of conversation repository.
 */
class EloquentConversationRepository implements ConversationRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function getConversations(?string $userId, int $limit = 50, int $offset = 0): array
    {
        $query = BotovisConversation::query()
            ->with('lastMessage')
            ->orderByDesc('updated_at');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $conversations = $query->skip($offset)->take($limit)->get();

        return $conversations->map(fn($conv) => $this->toConversationDTO($conv, false))->all();
    }

    /**
     * @inheritDoc
     */
    public function getConversation(string $id, bool $withMessages = true): ?Conversation
    {
        $query = BotovisConversation::query();

        if ($withMessages) {
            $query->with('messages');
        }

        $conversation = $query->find($id);

        if (!$conversation) {
            return null;
        }

        return $this->toConversationDTO($conversation, $withMessages);
    }

    /**
     * @inheritDoc
     */
    public function createConversation(?string $userId, string $title = 'Yeni Sohbet', array $metadata = []): Conversation
    {
        $conversation = BotovisConversation::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $userId,
            'title' => $title,
            'metadata' => $metadata,
        ]);

        return $this->toConversationDTO($conversation, true);
    }

    /**
     * @inheritDoc
     */
    public function updateTitle(string $conversationId, string $title): bool
    {
        return BotovisConversation::where('id', $conversationId)
            ->update(['title' => $title]) > 0;
    }

    /**
     * @inheritDoc
     */
    public function deleteConversation(string $conversationId): bool
    {
        return BotovisConversation::destroy($conversationId) > 0;
    }

    /**
     * @inheritDoc
     */
    public function getMessages(string $conversationId, int $limit = 100, int $offset = 0): array
    {
        $messages = BotovisMessage::where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $messages->map(fn($msg) => $this->toMessageDTO($msg))->all();
    }

    /**
     * @inheritDoc
     */
    public function addMessage(Message $message): Message
    {
        $model = BotovisMessage::create([
            'id' => $message->id ?: Str::uuid()->toString(),
            'conversation_id' => $message->conversationId,
            'role' => $message->role,
            'content' => $message->content,
            'intent' => $message->intent,
            'action' => $message->action,
            'table' => $message->table,
            'parameters' => $message->parameters,
            'success' => $message->success,
            'execution_time_ms' => $message->executionTimeMs,
            'metadata' => $message->metadata,
            'created_at' => $message->createdAt ?? now(),
        ]);

        // Update conversation's updated_at
        BotovisConversation::where('id', $message->conversationId)
            ->update(['updated_at' => now()]);

        return $this->toMessageDTO($model);
    }

    /**
     * @inheritDoc
     */
    public function getLastMessages(string $conversationId, int $count = 10): array
    {
        $messages = BotovisMessage::where('conversation_id', $conversationId)
            ->orderByDesc('created_at')
            ->take($count)
            ->get()
            ->reverse()
            ->values();

        return $messages->map(fn($msg) => $this->toMessageDTO($msg))->all();
    }

    /**
     * @inheritDoc
     */
    public function belongsToUser(string $conversationId, ?string $userId): bool
    {
        return BotovisConversation::where('id', $conversationId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Convert Eloquent model to Conversation DTO.
     */
    private function toConversationDTO(BotovisConversation $model, bool $withMessages): Conversation
    {
        $messages = [];
        if ($withMessages && $model->relationLoaded('messages')) {
            $messages = $model->messages->map(fn($msg) => $this->toMessageDTO($msg))->all();
        }

        return new Conversation(
            id: $model->id,
            userId: $model->user_id,
            title: $model->title,
            messages: $messages,
            createdAt: $model->created_at ? new DateTimeImmutable($model->created_at->toDateTimeString()) : null,
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at->toDateTimeString()) : null,
            metadata: $model->metadata ?? [],
        );
    }

    /**
     * Convert Eloquent model to Message DTO.
     */
    private function toMessageDTO(BotovisMessage $model): Message
    {
        return new Message(
            id: $model->id,
            conversationId: $model->conversation_id,
            role: $model->role,
            content: $model->content,
            intent: $model->intent,
            action: $model->action,
            table: $model->table,
            parameters: $model->parameters ?? [],
            success: $model->success,
            executionTimeMs: $model->execution_time_ms,
            createdAt: $model->created_at ? new DateTimeImmutable($model->created_at->toDateTimeString()) : null,
            metadata: $model->metadata ?? [],
        );
    }
}
