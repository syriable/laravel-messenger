<?php

namespace Syriable\Messenger\Support;

use Syriable\Messenger\Contracts\MessengerParticipant;

/**
 * Builds the deterministic, order-independent signature that guarantees a
 * single conversation can ever exist between any two participants.
 */
class ConversationKey
{
    public static function for(MessengerParticipant $a, MessengerParticipant $b): string
    {
        $tokens = [
            self::token($a),
            self::token($b),
        ];

        // Sorting makes the key independent of who initiated the conversation.
        sort($tokens);

        return implode('|', $tokens);
    }

    private static function token(MessengerParticipant $participant): string
    {
        return $participant->getMorphClass().'#'.$participant->getKey();
    }
}
