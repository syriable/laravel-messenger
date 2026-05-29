<?php

namespace Syriable\Messenger\Data;

/**
 * Immutable result of persisting a single uploaded file to disk, ready to be
 * written to the message_attachments table.
 */
class StoredAttachment
{
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly string $name,
        public readonly string $mimeType,
        public readonly ?string $extension,
        public readonly int $size,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'disk' => $this->disk,
            'path' => $this->path,
            'name' => $this->name,
            'mime_type' => $this->mimeType,
            'extension' => $this->extension,
            'size' => $this->size,
        ];
    }
}
