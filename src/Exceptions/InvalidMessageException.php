<?php

namespace Syriable\Messenger\Exceptions;

class InvalidMessageException extends MessengerException
{
    public static function empty(): self
    {
        return new self('A message must contain a body, at least one attachment, or both.');
    }
}
