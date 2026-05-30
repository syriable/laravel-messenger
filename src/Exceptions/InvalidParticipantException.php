<?php

namespace Syriable\Messenger\Exceptions;

class InvalidParticipantException extends MessengerException
{
    public static function sameParticipant(): self
    {
        return new self('A participant cannot start a conversation with itself.');
    }

    public static function notInConversation(): self
    {
        return new self('The given participant does not belong to this conversation.');
    }

    public static function doesNotExist(): self
    {
        return new self('The given participant does not exist.');
    }
}
