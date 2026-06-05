<?php

use Syriable\Messenger\Contracts\ParticipantPresenter;
use Syriable\Messenger\Support\DefaultParticipantPresenter;
use Syriable\Messenger\Tests\Models\User;

/**
 * The DefaultParticipantPresenter resolves a participant's display identity
 * without the domain assuming any host schema (#F1.2). It prefers an opt-in
 * method on the model, then a list of conventional attributes, then a graceful
 * fallback — and never throws.
 */
beforeEach(function () {
    $this->presenter = new DefaultParticipantPresenter;
});

it('is bound in the container and resolves the default implementation', function () {
    expect(app(ParticipantPresenter::class))->toBeInstanceOf(DefaultParticipantPresenter::class);
});

it('honours a custom presenter bound via config', function () {
    config()->set('messenger.presenter', CustomTestPresenter::class);

    expect(app(ParticipantPresenter::class))->toBeInstanceOf(CustomTestPresenter::class);
});

it('resolves the display name from a conventional attribute', function () {
    $user = User::factory()->create(['name' => 'Nancy C']);

    expect($this->presenter->displayName($user))->toBe('Nancy C');
});

it('falls back to a generated label when no name is present', function () {
    $user = User::factory()->create(['name' => null]);

    expect($this->presenter->displayName($user))->toBe("User #{$user->getKey()}");
});

it('reads avatar, handle and timezone from conventional attributes', function () {
    $user = User::factory()->create(['name' => 'Nancy C']);
    // Set in-memory (un-persisted) conventional attributes; $guarded = [].
    $user->avatar_url = 'https://cdn.example.test/nancy.png';
    $user->username = 'thedesignaffair';
    $user->timezone = 'Asia/Kolkata';

    expect($this->presenter->avatarUrl($user))->toBe('https://cdn.example.test/nancy.png')
        ->and($this->presenter->handle($user))->toBe('thedesignaffair')
        ->and($this->presenter->timezone($user))->toBe('Asia/Kolkata');
});

it('returns null for optional fields that are absent', function () {
    $user = User::factory()->create(['name' => 'Nancy C']);

    expect($this->presenter->avatarUrl($user))->toBeNull()
        ->and($this->presenter->handle($user))->toBeNull()
        ->and($this->presenter->profileUrl($user))->toBeNull()
        ->and($this->presenter->timezone($user))->toBeNull();
});

it('prefers an opt-in model method over conventional attributes', function () {
    $user = new PresenterAwareUser(['name' => 'ignored attribute']);

    expect($this->presenter->displayName($user))->toBe('Method Wins')
        ->and($this->presenter->handle($user))->toBe('@method-handle');
});

it('ignores blank attribute values and continues to the next candidate', function () {
    $user = User::factory()->create(['name' => '   ']);

    // Whitespace-only name is treated as absent, so it falls through to the label.
    expect($this->presenter->displayName($user))->toBe("User #{$user->getKey()}");
});

/**
 * A participant model that exposes explicit presentation methods, which must
 * take precedence over attribute conventions.
 */
class PresenterAwareUser extends User
{
    protected $table = 'users';

    public function messengerDisplayName(): string
    {
        return 'Method Wins';
    }

    public function messengerHandle(): string
    {
        return '@method-handle';
    }
}

/**
 * A trivial custom presenter used to prove the config binding is honoured.
 */
class CustomTestPresenter extends DefaultParticipantPresenter {}
