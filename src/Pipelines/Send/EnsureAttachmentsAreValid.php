<?php

namespace Syriable\Messenger\Pipelines\Send;

use Closure;
use Illuminate\Http\UploadedFile;
use Syriable\Messenger\Contracts\SendPipe;
use Syriable\Messenger\Data\PendingMessage;
use Syriable\Messenger\Exceptions\InvalidAttachmentException;

/**
 * Validates attachment count, size and type against the configurable limits in
 * the `messenger.attachments` config.
 */
class EnsureAttachmentsAreValid implements SendPipe
{
    public function handle(PendingMessage $message, Closure $next): PendingMessage
    {
        $attachments = $message->message->attachments;

        if ($attachments === []) {
            return $next($message);
        }

        $max = (int) config('messenger.attachments.max_per_message', 10);

        if (count($attachments) > $max) {
            throw InvalidAttachmentException::tooMany($max);
        }

        $maxSize = (int) config('messenger.attachments.max_size', 10240);
        $allowedMimes = array_map('strtolower', (array) config('messenger.attachments.allowed_mimes', []));
        $allowedExtensions = array_map('strtolower', (array) config('messenger.attachments.allowed_extensions', []));

        foreach ($attachments as $file) {
            if (! $file instanceof UploadedFile) {
                throw InvalidAttachmentException::disallowedType('unknown');
            }

            $name = $file->getClientOriginalName();

            // Reject uploads whose size cannot be determined rather than letting
            // a false/null size slip past the numeric comparison below.
            $size = $file->getSize();

            if (! is_int($size)) {
                throw InvalidAttachmentException::indeterminateSize($name);
            }

            // Reject empty (zero-byte) uploads unless explicitly allowed.
            if ($size <= 0 && ! config('messenger.attachments.allow_empty', false)) {
                throw InvalidAttachmentException::empty($name);
            }

            // Size limit (config value is in kilobytes).
            if ($maxSize > 0 && $size > $maxSize * 1024) {
                throw InvalidAttachmentException::tooLarge($name, $maxSize);
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());
            $mime = strtolower((string) ($file->getClientMimeType() ?: $file->getMimeType()));

            $extensionOk = $allowedExtensions === [] || in_array($extension, $allowedExtensions, true);
            $mimeOk = $allowedMimes === [] || in_array($mime, $allowedMimes, true);

            if (! $extensionOk || ! $mimeOk) {
                throw InvalidAttachmentException::disallowedType($name);
            }

            // Optional defence against type/extension spoofing: verify the
            // server-detected (content-sniffed) mime against the allow-list too,
            // so a payload renamed to a permitted extension is still rejected.
            if ($allowedMimes !== [] && config('messenger.attachments.verify_real_mime', false)) {
                $realMime = strtolower((string) $file->getMimeType());

                if ($realMime !== '' && ! in_array($realMime, $allowedMimes, true)) {
                    throw InvalidAttachmentException::disallowedType($name);
                }
            }
        }

        return $next($message);
    }
}
