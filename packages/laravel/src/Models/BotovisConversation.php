<?php

declare(strict_types=1);

namespace Botovis\Laravel\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent model for conversation storage.
 *
 * @property string $id
 * @property string|null $user_id
 * @property string $title
 * @property string|null $session_id
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class BotovisConversation extends Model
{
    use HasUuids;

    protected $table = 'botovis_conversations';

    protected $fillable = [
        'user_id',
        'title',
        'session_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get messages for this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(BotovisMessage::class, 'conversation_id')->orderBy('created_at');
    }

    /**
     * Get the last message.
     */
    public function lastMessage(): HasMany
    {
        return $this->hasMany(BotovisMessage::class, 'conversation_id')->latest('created_at')->limit(1);
    }
}
