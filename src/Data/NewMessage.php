<?php

namespace Syriable\Messenger\Data;

use Illuminate\Http\UploadedFile;
use Syriable\Messenger\Models\Message;

/**
 * Immutable description of a message a participant intends to send.
 *
 * A message is valid when it carries a non-empty body, at least one attachment,
 * or both. This DTO only captures intent; validation happens in the send
 * pipeline and persistence happens in the SendMessageAction.
 */
class NewMessage
{
    /**
     * Attachments are unvalidated input until the send pipeline runs; the
     * AttachmentService and EnsureAttachmentsAreValid pipe expect UploadedFile.
     *
     * @param  array<int, mixed>  $attachments
     */
    public function __construct(
        public readonly ?string $body = null,
        public readonly array $attachments = [],
        public readonly ?string $replyToId = null,
    ) {}

    /**
     * Build a NewMessage from a plain string, an array payload or an existing
     * instance.
     *
     * Accepted array keys: body, attachments, reply_to / replyTo / replyToId.
     *
     * @param  string|array<string, mixed>|self  $payload
     */
    public static function from(string|array|self $payload): self
    {
        if ($payload instanceof self) {
            return $payload;
        }

        if (is_string($payload)) {
            return new self(body: self::normalizeBody($payload));
        }

        $reply = $payload['reply_to'] ?? $payload['replyTo'] ?? $payload['replyToId'] ?? null;

        return new self(
            body: self::normalizeBody($payload['body'] ?? null),
            attachments: array_values($payload['attachments'] ?? []),
            replyToId: $reply instanceof Message ? $reply->getKey() : $reply,
        );
    }

    public function hasBody(): bool
    {
        return $this->body !== null && $this->body !== '';
    }

    public function hasAttachments(): bool
    {
        return count($this->attachments) > 0;
    }

    private static function normalizeBody(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $body = trim($body);

        return $body === '' ? null : $body;
    }
}
