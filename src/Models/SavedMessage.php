<?php

namespace Syriable\Messenger\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Support\HasPreciseTimestamps;
use Syriable\Messenger\Support\Models;

/**
 * A message bookmarked ("saved") by a participant. Per-participant and additive:
 * the saved set never affects the conversation or message itself.
 *
 * @property string $id
 * @property string $message_id
 * @property string $conversation_id
 * @property string $participant_type
 * @property string $participant_id
 */
class SavedMessage extends Model
{
    use HasPreciseTimestamps;
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('messenger.tables.saved', 'messenger_saved_messages');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Models::message(), 'message_id');
    }

    public function participant(): MorphTo
    {
        return $this->morphTo('participant');
    }

    /**
     * @param  Builder<SavedMessage>  $query
     * @return Builder<SavedMessage>
     */
    public function scopeForParticipant(Builder $query, MessengerParticipant $participant): Builder
    {
        return $query
            ->where('participant_type', $participant->getMorphClass())
            ->where('participant_id', $participant->getKey());
    }
}
