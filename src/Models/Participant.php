<?php

namespace Syriable\Messenger\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Support\HasPreciseTimestamps;
use Syriable\Messenger\Support\Models;

/**
 * Links a morphable participant to a conversation and holds all of that
 * participant's conversation-specific state.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string $participant_type
 * @property string $participant_id
 * @property ?Carbon $archived_at
 * @property ?Carbon $starred_at
 * @property ?Carbon $blocked_at
 * @property ?Carbon $spammed_at
 * @property ?Carbon $cleared_at
 * @property ?Carbon $last_read_at
 * @property int $unread_count
 */
class Participant extends Model
{
    use HasPreciseTimestamps;
    use HasUlids;

    /**
     * Only the identity columns may be mass-assigned. All participant-specific
     * state (`unread_count`, `blocked_at`, `spammed_at`, `archived_at`,
     * `starred_at`, `cleared_at`, `last_read_at`) is mutated exclusively through
     * the package actions via `forceFill()` / `increment()`, never from request
     * input, so it is deliberately not fillable to prevent tampering (#audit S1).
     *
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'participant_type',
        'participant_id',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'starred_at' => 'datetime',
        'blocked_at' => 'datetime',
        'spammed_at' => 'datetime',
        'cleared_at' => 'datetime',
        'last_read_at' => 'datetime',
        'unread_count' => 'integer',
    ];

    public function getTable(): string
    {
        return config('messenger.tables.participants', 'messenger_participants');
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Models::conversation(), 'conversation_id');
    }

    public function participant(): MorphTo
    {
        return $this->morphTo('participant');
    }

    /**
     * Scope to the participant rows belonging to a given participant model.
     *
     * @param  Builder<Participant>  $query
     * @return Builder<Participant>
     */
    public function scopeForParticipant(Builder $query, MessengerParticipant $participant): Builder
    {
        return $query
            ->where('participant_type', $participant->getMorphClass())
            ->where('participant_id', $participant->getKey());
    }

    /**
     * @param  Builder<Participant>  $query
     * @return Builder<Participant>
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<Participant>  $query
     * @return Builder<Participant>
     */
    public function scopeStarred(Builder $query): Builder
    {
        return $query->whereNotNull('starred_at');
    }

    /**
     * Whether this row represents the given participant model.
     */
    public function represents(MessengerParticipant $participant): bool
    {
        return $this->participant_type === $participant->getMorphClass()
            && (string) $this->participant_id === (string) $participant->getKey();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isStarred(): bool
    {
        return $this->starred_at !== null;
    }

    public function isBlocking(): bool
    {
        return $this->blocked_at !== null;
    }

    public function isSpamming(): bool
    {
        return $this->spammed_at !== null;
    }

    public function isUnread(): bool
    {
        return $this->unread_count > 0;
    }
}
