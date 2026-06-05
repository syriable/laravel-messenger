<?php

namespace Syriable\Messenger\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Support\HasPreciseTimestamps;
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
    use HasPreciseTimestamps;
    use HasUlids;

    /**
     * Attachments are persisted only by the send action from already-validated,
     * server-derived metadata. Limiting mass assignment to these columns keeps
     * request input from pointing a row at an arbitrary disk/path (#audit S1).
     *
     * @var list<string>
     */
    protected $fillable = [
        'message_id',
        'disk',
        'path',
        'name',
        'mime_type',
        'extension',
        'size',
    ];

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

    /**
     * SECURITY: this returns an UNSIGNED, public `Storage::url()`. It is only
     * appropriate for attachments stored on a public disk. For sensitive files
     * keep them on a private disk and serve them via {@see temporaryUrl()} (on a
     * driver that supports it, e.g. S3) or through your own authorized download
     * route. The package never gates file access for you (#audit S2).
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Generate a time-limited, signed URL for a private attachment, suitable for
     * handing to a browser without exposing the file permanently. Requires a
     * disk whose driver supports temporary URLs (e.g. S3); local/public disks
     * that cannot sign will throw, in which case serve the file through an
     * authorized download route instead.
     *
     * @param  array<string, mixed>  $options
     */
    public function temporaryUrl(DateTimeInterface|int $expiresIn = 5, array $options = []): string
    {
        $expiresAt = is_int($expiresIn)
            ? Carbon::now()->addMinutes($expiresIn)
            : $expiresIn;

        return Storage::disk($this->disk)->temporaryUrl($this->path, $expiresAt, $options);
    }
}
