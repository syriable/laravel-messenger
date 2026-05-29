<?php

namespace Syriable\Messenger\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Syriable\Messenger\Data\StoredAttachment;
use Syriable\Messenger\Exceptions\InvalidAttachmentException;

/**
 * Owns the attachment storage lifecycle: naming, persistence and metadata
 * extraction. Validation is handled separately by the send pipeline; this
 * service assumes the file has already been validated.
 */
class AttachmentService
{
    public function disk(): string
    {
        return config('messenger.attachments.disk', 'local');
    }

    public function directory(): string
    {
        return trim((string) config('messenger.attachments.directory', 'messenger/attachments'), '/');
    }

    /**
     * Store an uploaded file and return its persisted metadata.
     */
    public function store(UploadedFile $file): StoredAttachment
    {
        $disk = $this->disk();
        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension();
        $name = (string) Str::ulid();

        if ($extension) {
            $name .= '.'.$extension;
        }

        $path = $file->storeAs($this->directory(), $name, ['disk' => $disk]);

        // storeAs() returns false when the write fails (e.g. disk permissions).
        // Treat that as a hard failure so the send rolls back and cleans up.
        if ($path === false) {
            throw InvalidAttachmentException::storageFailed($file->getClientOriginalName());
        }

        return new StoredAttachment(
            disk: $disk,
            path: $path,
            name: $file->getClientOriginalName(),
            mimeType: $file->getClientMimeType() ?: ($file->getMimeType() ?: 'application/octet-stream'),
            extension: $extension ?: null,
            size: $file->getSize() ?: 0,
        );
    }
}
