<?php

namespace Syriable\Messenger\Exceptions;

class InvalidReportException extends MessengerException
{
    public static function reasonTooLong(int $max): self
    {
        return new self("The report reason may not be longer than {$max} characters.");
    }

    public static function noteTooLong(int $max): self
    {
        return new self("The report note may not be longer than {$max} characters.");
    }

    public static function notAParticipant(): self
    {
        return new self('Only a participant of the conversation may report this message.');
    }
}
