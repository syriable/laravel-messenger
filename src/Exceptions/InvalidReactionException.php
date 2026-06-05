<?php

namespace Syriable\Messenger\Exceptions;

class InvalidReactionException extends MessengerException
{
    public static function empty(): self
    {
        return new self('A reaction emoji must not be empty.');
    }

    public static function notAllowed(string $emoji): self
    {
        return new self("The reaction [{$emoji}] is not in the allowed set.");
    }
}
