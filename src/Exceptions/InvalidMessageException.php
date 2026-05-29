<?php

namespace Syriable\Messenger\Exceptions;

class InvalidMessageException extends MessengerException
{
    public static function empty(): self
    {
        return new self('A message must contain a body, at least one attachment, or both.');
    }

    public static function bodyTooLong(int $max): self
    {
        return new self("The message body may not be longer than {$max} characters.");
    }
}
