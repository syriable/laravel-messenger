<?php

namespace Syriable\Messenger\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Contracts\ParticipantPresenter;

/**
 * Convention-based {@see ParticipantPresenter} used when the host binds nothing
 * of its own. It resolves identity without assuming a concrete schema by, in
 * order of preference:
 *
 *   1. calling an opt-in method on the model (e.g. messengerDisplayName()), so a
 *      host can expose presentation explicitly on its participant models; then
 *   2. reading the first present, non-empty of a list of conventional
 *      attributes (name, avatar_url, username, …); then
 *   3. falling back to a generated label ("User #5") for the display name, or
 *      null for the optional fields.
 *
 * Every accessor is null-safe: a deleted participant, a non-Eloquent
 * participant, or a missing attribute yields a fallback rather than an error.
 */
class DefaultParticipantPresenter implements ParticipantPresenter
{
    public function displayName(MessengerParticipant $participant): string
    {
        if ($name = $this->viaMethod($participant, 'messengerDisplayName')) {
            return $name;
        }

        return $this->attribute($participant, ['name', 'display_name', 'full_name', 'username', 'nickname'])
            ?? $this->fallbackLabel($participant);
    }

    public function avatarUrl(MessengerParticipant $participant): ?string
    {
        return $this->viaMethod($participant, 'messengerAvatarUrl')
            ?? $this->attribute($participant, ['avatar_url', 'avatar', 'profile_photo_url', 'photo_url', 'image']);
    }

    public function handle(MessengerParticipant $participant): ?string
    {
        return $this->viaMethod($participant, 'messengerHandle')
            ?? $this->attribute($participant, ['username', 'handle', 'nickname', 'slug']);
    }

    public function profileUrl(MessengerParticipant $participant): ?string
    {
        return $this->viaMethod($participant, 'messengerProfileUrl')
            ?? $this->attribute($participant, ['profile_url']);
    }

    public function timezone(MessengerParticipant $participant): ?string
    {
        return $this->viaMethod($participant, 'messengerTimezone')
            ?? $this->attribute($participant, ['timezone', 'tz', 'time_zone']);
    }

    /**
     * Read the first present, non-empty string from a list of attribute names.
     *
     * @param  array<int, string>  $keys
     */
    protected function attribute(MessengerParticipant $participant, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->read($participant, $key);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Invoke an opt-in presentation method on the model if it exposes one.
     */
    protected function viaMethod(MessengerParticipant $participant, string $method): ?string
    {
        if (! method_exists($participant, $method)) {
            return null;
        }

        $value = $participant->{$method}();

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * Read a single attribute in a way that is safe for both Eloquent models
     * and arbitrary participant objects.
     */
    protected function read(MessengerParticipant $participant, string $key): mixed
    {
        if ($participant instanceof Model) {
            return $participant->getAttribute($key);
        }

        return isset($participant->{$key}) ? $participant->{$key} : null;
    }

    protected function fallbackLabel(MessengerParticipant $participant): string
    {
        $type = Str::headline(class_basename($participant->getMorphClass()));

        return trim("{$type} #{$participant->getKey()}");
    }
}
