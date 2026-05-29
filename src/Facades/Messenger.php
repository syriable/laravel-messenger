<?php

namespace Syriable\Messenger\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Syriable\Messenger\Models\Message send(\Syriable\Messenger\Contracts\MessengerParticipant $from, \Syriable\Messenger\Contracts\MessengerParticipant $to, string|array|\Syriable\Messenger\Data\NewMessage $message)
 * @method static ?\Syriable\Messenger\Models\Conversation between(\Syriable\Messenger\Contracts\MessengerParticipant $a, \Syriable\Messenger\Contracts\MessengerParticipant $b)
 * @method static \Illuminate\Support\Collection inbox(\Syriable\Messenger\Contracts\MessengerParticipant $participant, array $options = [])
 * @method static \Illuminate\Support\Collection messages(\Syriable\Messenger\Models\Conversation $conversation, \Syriable\Messenger\Contracts\MessengerParticipant $participant, array $options = [])
 * @method static int unreadCount(\Syriable\Messenger\Contracts\MessengerParticipant $participant)
 * @method static int unreadConversations(\Syriable\Messenger\Contracts\MessengerParticipant $participant)
 * @method static \Syriable\Messenger\Models\Participant archive(\Syriable\Messenger\Models\Conversation $conversation, \Syriable\Messenger\Contracts\MessengerParticipant $participant)
 * @method static \Syriable\Messenger\Models\Participant unarchive(\Syriable\Messenger\Models\Conversation $conversation, \Syriable\Messenger\Contracts\MessengerParticipant $participant)
 * @method static \Syriable\Messenger\Models\Participant star(\Syriable\Messenger\Models\Conversation $conversation, \Syriable\Messenger\Contracts\MessengerParticipant $participant)
 * @method static \Syriable\Messenger\Models\Participant unstar(\Syriable\Messenger\Models\Conversation $conversation, \Syriable\Messenger\Contracts\MessengerParticipant $participant)
 * @method static \Syriable\Messenger\Models\Participant block(\Syriable\Messenger\Models\Conversation $conversation, \Syriable\Messenger\Contracts\MessengerParticipant $participant)
 * @method static \Syriable\Messenger\Models\Participant unblock(\Syriable\Messenger\Models\Conversation $conversation, \Syriable\Messenger\Contracts\MessengerParticipant $participant)
 * @method static \Syriable\Messenger\Models\Participant spam(\Syriable\Messenger\Models\Conversation $conversation, \Syriable\Messenger\Contracts\MessengerParticipant $participant)
 * @method static \Syriable\Messenger\Models\Participant unspam(\Syriable\Messenger\Models\Conversation $conversation, \Syriable\Messenger\Contracts\MessengerParticipant $participant)
 * @method static \Syriable\Messenger\Models\Participant clear(\Syriable\Messenger\Models\Conversation $conversation, \Syriable\Messenger\Contracts\MessengerParticipant $participant)
 * @method static \Syriable\Messenger\Models\Participant markAsRead(\Syriable\Messenger\Models\Conversation $conversation, \Syriable\Messenger\Contracts\MessengerParticipant $participant)
 * @method static \Syriable\Messenger\Models\Participant markAsUnread(\Syriable\Messenger\Models\Conversation $conversation, \Syriable\Messenger\Contracts\MessengerParticipant $participant)
 * @method static \Syriable\Messenger\Models\MessageReport report(\Syriable\Messenger\Models\Message $message, \Syriable\Messenger\Contracts\MessengerParticipant $reporter, ?string $reason = null, ?string $note = null)
 *
 * @see \Syriable\Messenger\Messenger
 */
class Messenger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Syriable\Messenger\Messenger::class;
    }
}
