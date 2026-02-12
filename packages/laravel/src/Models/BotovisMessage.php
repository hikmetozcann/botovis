<?php

declare(strict_types=1);

namespace Botovis\Laravel\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for message storage.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string $role
 * @property string $content
 * @property string|null $intent
 * @property string|null $action
 * @property string|null $table
 * @property array|null $parameters
 * @property bool|null $success
 * @property int|null $execution_time_ms
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 */
class BotovisMessage extends Model
{
    use HasUuids;

    protected $table = 'botovis_messages';

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'intent',
        'action',
        'table',
        'parameters',
        'success',
        'execution_time_ms',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'metadata' => 'array',
        'success' => 'boolean',
        'execution_time_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get the conversation this message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(BotovisConversation::class, 'conversation_id');
    }

    /**
     * Check if this is a user message.
     */
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check if this is an assistant message.
     */
    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }
}
