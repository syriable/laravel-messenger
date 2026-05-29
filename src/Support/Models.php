<?php

namespace Syriable\Messenger\Support;

use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Models\MessageAttachment;
use Syriable\Messenger\Models\MessageReport;
use Syriable\Messenger\Models\Participant;

/**
 * Central resolver for the package's Eloquent models.
 *
 * Models may be swapped for host-application subclasses via the
 * `messenger.models` config. Resolve through these helpers rather than
 * referencing concrete classes so overrides are always honoured.
 */
class Models
{
    /** @return class-string<Conversation> */
    public static function conversation(): string
    {
        return config('messenger.models.conversation', Conversation::class);
    }

    /** @return class-string<Participant> */
    public static function participant(): string
    {
        return config('messenger.models.participant', Participant::class);
    }

    /** @return class-string<Message> */
    public static function message(): string
    {
        return config('messenger.models.message', Message::class);
    }

    /** @return class-string<MessageAttachment> */
    public static function attachment(): string
    {
        return config('messenger.models.attachment', MessageAttachment::class);
    }

    /** @return class-string<MessageReport> */
    public static function report(): string
    {
        return config('messenger.models.report', MessageReport::class);
    }

    public static function newConversation(): Conversation
    {
        $class = static::conversation();

        return new $class;
    }

    public static function newParticipant(): Participant
    {
        $class = static::participant();

        return new $class;
    }

    public static function newMessage(): Message
    {
        $class = static::message();

        return new $class;
    }
}
