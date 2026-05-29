<?php

namespace Syriable\Messenger\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Support\Models;

/**
 * A file attached directly to a message. Attachments are not polymorphic in v1.
 *
 * @property string $id
 * @property string $message_id
 * @property string $disk
 * @property string $path
 * @property string $name
 * @property string $mime_type
 * @property ?string $extension
 * @property int $size
 */
class MessageAttachment extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'size' => 'integer',
    ];

    public function getTable(): string
    {
        return config('messenger.tables.attachments', 'messenger_message_attachments');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Models::message(), 'message_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
