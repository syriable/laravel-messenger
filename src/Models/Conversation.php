<?php

namespace Syriable\Messenger\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Support\HasPreciseTimestamps;
use Syriable\Messenger\Support\Models;

/**
 * A persistent, shared one-to-one conversation thread.
 *
 * The conversation itself stays neutral: participant-specific state (archived,
 * starred, blocked, cleared, unread, ...) lives on the {@see Participant} rows.
 *
 * @property string $id
 * @property string $key
 * @property ?string $last_message_id
 * @property ?Carbon $last_message_at
 */
class Conversation extends Model
{
    use HasPreciseTimestamps;
    use HasUlids;

    /**
     * Only the deterministic conversation key may be mass-assigned. The
     * denormalised projection columns (`last_message_id`, `last_message_at`) are
     * written exclusively through the package actions via `forceFill()`, never
     * from request input, so they are deliberately not fillable (#audit S1).
     *
     * @var list<string>
     */
    protected $fillable = ['key'];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('messenger.tables.conversations', 'messenger_conversations');
    }

    /**
     * @return HasMany<Participant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(Models::participant(), 'conversation_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Models::message(), 'conversation_id');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Models::message(), 'last_message_id');
    }

    /**
     * The participant row for the given participant model, if any.
     */
    public function participantFor(MessengerParticipant $participant): ?Participant
    {
        return $this->participants
            ->first(fn (Participant $row) => $row->represents($participant));
    }

    /**
     * The participant row for the other side of the conversation.
     */
    public function otherParticipantFor(MessengerParticipant $participant): ?Participant
    {
        return $this->participants
            ->first(fn (Participant $row) => ! $row->represents($participant));
    }
}
