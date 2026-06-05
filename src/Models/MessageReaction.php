<?php

namespace Syriable\Messenger\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Syriable\Messenger\Support\HasPreciseTimestamps;
use Syriable\Messenger\Support\Models;

/**
 * An emoji reaction left on a message by a participant. Per-participant and
 * additive; a participant may leave several distinct emojis on a message, each
 * at most once.
 *
 * @property string $id
 * @property string $message_id
 * @property string $conversation_id
 * @property string $participant_type
 * @property string $participant_id
 * @property string $emoji
 */
class MessageReaction extends Model
{
    use HasPreciseTimestamps;
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('messenger.tables.reactions', 'messenger_message_reactions');
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
}
