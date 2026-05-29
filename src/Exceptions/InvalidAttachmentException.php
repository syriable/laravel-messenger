<?php

namespace Syriable\Messenger\Exceptions;

class InvalidAttachmentException extends MessengerException
{
    public static function tooMany(int $max): self
    {
        return new self("A message may not have more than {$max} attachments.");
    }

    public static function tooLarge(string $name, int $maxKilobytes): self
    {
        return new self("Attachment [{$name}] exceeds the maximum size of {$maxKilobytes} KB.");
    }

    public static function disallowedType(string $name): self
    {
        return new self("Attachment [{$name}] has a file type that is not allowed.");
    }

    public static function indeterminateSize(string $name): self
    {
        return new self("Attachment [{$name}] could not be accepted because its size could not be determined.");
    }

    public static function storageFailed(string $name): self
    {
        return new self("Attachment [{$name}] could not be stored.");
    }
}
