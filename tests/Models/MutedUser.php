<?php

namespace Syriable\Messenger\Tests\Models;

use Syriable\Messenger\Models\Message;

/**
 * A participant who has muted messenger notifications, demonstrating the
 * host-owned opt-out hook used by the NotifyRecipient listener.
 */
class MutedUser extends User
{
    protected $table = 'users';

    public function shouldReceiveMessengerNotification(Message $message): bool
    {
        return false;
    }
}
