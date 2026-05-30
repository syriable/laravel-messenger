<?php

namespace Syriable\Messenger\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Syriable\Messenger\Support\HasPreciseTimestamps;
use Syriable\Messenger\Support\Models;

/**
 * A report raised by a participant against a specific message. Reporting is
 * message-based, never conversation-based.
 *
 * @property string $id
 * @property string $message_id
 * @property string $reporter_type
 * @property string $reporter_id
 * @property ?string $reason
 * @property ?string $note
 */
class MessageReport extends Model
{
    use HasPreciseTimestamps;
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('messenger.tables.reports', 'messenger_message_reports');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Models::message(), 'message_id');
    }

    public function reporter(): MorphTo
    {
        return $this->morphTo('reporter');
    }
}
