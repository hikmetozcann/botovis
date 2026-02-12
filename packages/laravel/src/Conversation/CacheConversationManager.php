<?php

declare(strict_types=1);

namespace Botovis\Laravel\Conversation;

use Botovis\Core\Contracts\ConversationManagerInterface;
use Botovis\Core\Conversation\ConversationState;
use Illuminate\Support\Facades\Cache;

/**
 * Stores Botovis conversations in Laravel's cache system.
 *
 * Works with any cache driver (Redis, file, database, Memcached, etc.).
 * Each conversation is stored as a serialized ConversationState with a TTL.
 */
class CacheConversationManager implements ConversationManagerInterface
{
    private const PREFIX = 'botovis:conv:';
    private const TTL_MINUTES = 60;

    public function get(string $conversationId): ConversationState
    {
        $key = self::PREFIX . $conversationId;
        $state = Cache::get($key);

        if ($state instanceof ConversationState) {
            return $state;
        }

        return new ConversationState();
    }

    public function save(string $conversationId, ConversationState $state): void
    {
        $key = self::PREFIX . $conversationId;
        Cache::put($key, $state, now()->addMinutes(self::TTL_MINUTES));
    }

    public function delete(string $conversationId): void
    {
        $key = self::PREFIX . $conversationId;
        Cache::forget($key);
    }

    public function exists(string $conversationId): bool
    {
        $key = self::PREFIX . $conversationId;
        return Cache::has($key);
    }
}
