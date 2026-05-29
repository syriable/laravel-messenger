<?php

namespace Syriable\Messenger\Support;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Data\NewMessage;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Models\Participant;

/**
 * Drop-in implementation of {@see MessengerParticipant} for host models.
 *
 * Convenience methods delegate to the Messenger facade so all business logic
 * stays in the actions/queries layer and host models remain thin.
 */
trait Messageable
{
    /**
     * @return MorphMany<Participant, $this>
     */
    public function messengerParticipations(): MorphMany
    {
        return $this->morphMany(Models::participant(), 'participant');
    }

    /**
     * Send a message to another participant. The conversation is created
     * automatically on the first message.
     *
     * @param  string|array<string, mixed>|NewMessage  $message
     */
    public function sendMessageTo(MessengerParticipant $recipient, string|array|NewMessage $message): Message
    {
        return Messenger::send($this, $recipient, $message);
    }

    /**
     * The existing conversation with another participant, if any.
     */
    public function conversationWith(MessengerParticipant $other): ?Conversation
    {
        return Messenger::between($this, $other);
    }

    /**
     * This participant's inbox, ordered by latest activity.
     *
     * @return Collection<int, Conversation>
     */
    public function inbox(array $options = []): Collection
    {
        return Messenger::inbox($this, $options);
    }

    /**
     * Total number of unread messages across the participant's conversations.
     */
    public function unreadMessagesCount(): int
    {
        return Messenger::unreadCount($this);
    }

    /**
     * Number of conversations that contain at least one unread message.
     */
    public function unreadConversationsCount(): int
    {
        return Messenger::unreadConversations($this);
    }
}
