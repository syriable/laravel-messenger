<?php

namespace Syriable\Messenger\Exceptions;

class ConversationBlockedException extends MessengerException
{
    public static function make(): self
    {
        return new self('Messages cannot be sent because the conversation is blocked or marked as spam.');
    }
}
