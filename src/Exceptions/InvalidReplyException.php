<?php

namespace Syriable\Messenger\Exceptions;

class InvalidReplyException extends MessengerException
{
    public static function notInConversation(): self
    {
        return new self('The replied-to message does not exist in this conversation.');
    }
}
