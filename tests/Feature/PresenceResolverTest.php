<?php

use Syriable\Messenger\Contracts\PresenceResolver;
use Syriable\Messenger\Support\NullPresenceResolver;
use Syriable\Messenger\Tests\Models\User;
use Syriable\Messenger\Tests\Support\FakeOnlinePresenceResolver;

/**
 * Presence is a transport concern resolved through a swappable contract (E6 /
 * F1.5). The default reports everyone offline; hosts bind their own.
 */
it('binds the null presence resolver by default', function () {
    expect(app(PresenceResolver::class))->toBeInstanceOf(NullPresenceResolver::class);
});

it('reports offline with no last-seen by default', function () {
    $user = User::factory()->create();
    $resolver = app(PresenceResolver::class);

    expect($resolver->status($user))->toBe('offline')
        ->and($resolver->lastSeenAt($user))->toBeNull();
});

it('honours a custom presence resolver bound via config', function () {
    config()->set('messenger.ui.presence_resolver', FakeOnlinePresenceResolver::class);

    $resolver = app(PresenceResolver::class);

    expect($resolver)->toBeInstanceOf(FakeOnlinePresenceResolver::class)
        ->and($resolver->status(User::factory()->create()))->toBe('online');
});
