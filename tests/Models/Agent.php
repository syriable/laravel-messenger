<?php

namespace Syriable\Messenger\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Support\Messageable;
use Syriable\Messenger\Tests\Database\Factories\AgentFactory;

/**
 * A second, distinct morph participant type. Demonstrates that different model
 * types (e.g. a User and a support Agent) can converse with each other, and
 * guards cross-model morph handling against regression.
 */
class Agent extends Model implements MessengerParticipant
{
    use HasFactory;
    use Messageable;

    protected $guarded = [];

    public $timestamps = true;

    protected static function newFactory(): AgentFactory
    {
        return AgentFactory::new();
    }
}
