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
        $type = $participant->getMorphClass();
        $id = (string) $participant->getKey();

        // Length-prefix each segment so the separator characters can never be
        // confused with content — distinct participant pairs can never collide,
        // even with custom morph aliases or keys containing `#` or `|`.
        return strlen($type).':'.$type.'#'.strlen($id).':'.$id;
    }
}
