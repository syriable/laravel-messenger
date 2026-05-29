<?php

namespace Syriable\Messenger\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Syriable\Messenger\Support\Models;

/**
 * An immutable message within a conversation.
 *
 * Messages are never edited, deleted or unsent in v1. They are ordered
 * chronologically, with the newest message at the bottom of the timeline.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string $sender_type
 * @property string $sender_id
 * @property ?string $body
 * @property ?string $reply_to_id
 * @property Carbon $created_at
 */
class Message extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('messenger.tables.messages', 'messenger_messages');
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Models::conversation(), 'conversation_id');
    }

    public function sender(): MorphTo
    {
        return $this->morphTo('sender');
    }

    /**
     * @return HasMany<MessageAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Models::attachment(), 'message_id');
    }

    /**
     * @return HasMany<MessageReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Models::report(), 'message_id');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Models::message(), 'reply_to_id');
    }

    /**
     * Order chronologically (newest at the bottom of the timeline).
     *
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('created_at')->orderBy('id');
    }

    public function hasAttachments(): bool
    {
        return $this->attachments->isNotEmpty();
    }
}
