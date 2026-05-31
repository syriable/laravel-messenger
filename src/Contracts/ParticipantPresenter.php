<?php

namespace Syriable\Messenger\Contracts;

/**
 * Resolves the display identity of a morphable participant for presentation.
 *
 * The messaging domain only stores a participant's morph type and key — it
 * deliberately knows nothing about how the host models names, avatars, handles,
 * profile URLs or timezones. Any UI (the Livewire/Filament packages, or a host's
 * own front-end) needs those details to render a conversation, so the package
 * exposes this contract as the single, swappable boundary for resolving them.
 *
 * Bind your own implementation to override the convention-based default:
 *
 *     $this->app->bind(ParticipantPresenter::class, MyPresenter::class);
 *
 * or point the `messenger.presenter` config at your class. Implementations must
 * tolerate a participant whose underlying model has been deleted (the morphTo
 * target resolves to null) and never throw — return sensible fallbacks instead.
 */
interface ParticipantPresenter
{
    /**
     * A human-readable name for the participant. Never empty — fall back to a
     * generated label (e.g. "User #5") when no name is available.
     */
    public function displayName(MessengerParticipant $participant): string;

    /**
     * An absolute URL to the participant's avatar, or null when none is known.
     */
    public function avatarUrl(MessengerParticipant $participant): ?string;

    /**
     * A short handle / username (e.g. "@thedesignaffair"), or null.
     */
    public function handle(MessengerParticipant $participant): ?string;

    /**
     * A URL to the participant's profile page, or null.
     */
    public function profileUrl(MessengerParticipant $participant): ?string;

    /**
     * The participant's IANA timezone (e.g. "Asia/Damascus") for local-time
     * display ("last seen … local time"), or null to use the app default.
     */
    public function timezone(MessengerParticipant $participant): ?string;
}
