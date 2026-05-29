<?php

namespace Syriable\Messenger\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Syriable\Messenger\Support\Messageable;

/**
 * Marker contract implemented by any Eloquent model that may take part in a
 * conversation (users, admins, sellers, support agents, ...).
 *
 * The bundled {@see Messageable} trait provides a
 * complete implementation. Participants are always morphable; the package never
 * assumes a concrete App\Models\User.
 *
 * @property-read int|string $id
 */
interface MessengerParticipant
{
    /**
     * The participant rows that link this model into conversations.
     */
    public function messengerParticipations(): MorphMany;

    /**
     * The morph alias / class used to store this participant polymorphically.
     */
    public function getMorphClass();

    /**
     * The primary key value used to store this participant polymorphically.
     */
    public function getKey();
}
