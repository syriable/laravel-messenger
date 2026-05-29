<?php

namespace Syriable\Messenger\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Support\Messageable;
use Syriable\Messenger\Tests\Database\Factories\UserFactory;

/**
 * Test participant model. Demonstrates that any morphable Eloquent model can
 * take part in conversations simply by implementing MessengerParticipant via
 * the Messageable trait.
 */
class User extends Authenticatable implements MessengerParticipant
{
    use HasFactory;
    use Messageable;

    protected $guarded = [];

    public $timestamps = true;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
