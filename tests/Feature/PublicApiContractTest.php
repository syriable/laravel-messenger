<?php

use Syriable\Messenger\Messenger;

/**
 * Backward-compatibility contract guard (#F1.10).
 *
 * The package is consumed headlessly by host applications and, going forward,
 * by the separate UI and Filament packages. This test snapshots the public
 * surface — the Messenger service methods and the domain events — so that an
 * accidental signature change, rename or removal fails fast in CI. New methods
 * may be added freely; existing ones must keep their name, required-parameter
 * count and return type. Update this snapshot only as a deliberate, reviewed
 * change.
 */

/**
 * The frozen public API of the Messenger service: method => [requiredParams,
 * returnType]. requiredParams counts parameters without a default value.
 */
function messengerPublicApi(): array
{
    return [
        'send' => [3, 'Syriable\Messenger\Models\Message'],
        'between' => [2, '?Syriable\Messenger\Models\Conversation'],
        'inbox' => [1, 'Illuminate\Support\Collection'],
        'messages' => [2, 'Illuminate\Support\Collection'],
        'unreadCount' => [1, 'int'],
        'unreadConversations' => [1, 'int'],
        'archive' => [2, 'Syriable\Messenger\Models\Participant'],
        'unarchive' => [2, 'Syriable\Messenger\Models\Participant'],
        'star' => [2, 'Syriable\Messenger\Models\Participant'],
        'unstar' => [2, 'Syriable\Messenger\Models\Participant'],
        'block' => [2, 'Syriable\Messenger\Models\Participant'],
        'unblock' => [2, 'Syriable\Messenger\Models\Participant'],
        'spam' => [2, 'Syriable\Messenger\Models\Participant'],
        'unspam' => [2, 'Syriable\Messenger\Models\Participant'],
        'clear' => [2, 'Syriable\Messenger\Models\Participant'],
        'markAsRead' => [2, 'Syriable\Messenger\Models\Participant'],
        'markAsUnread' => [2, 'Syriable\Messenger\Models\Participant'],
        'report' => [2, 'Syriable\Messenger\Models\MessageReport'],
        'save' => [2, 'Syriable\Messenger\Models\SavedMessage'],
        'unsave' => [2, 'void'],
        'saved' => [1, 'Illuminate\Support\Collection'],
        'isSaved' => [2, 'bool'],
        'pruneAttachments' => [0, 'Illuminate\Support\Collection'],
    ];
}

it('keeps the public Messenger API stable', function (string $method, int $requiredParams, string $returnType) {
    expect(method_exists(Messenger::class, $method))->toBeTrue("Messenger::{$method}() must exist");

    $reflection = new ReflectionMethod(Messenger::class, $method);

    expect($reflection->isPublic())->toBeTrue("Messenger::{$method}() must stay public")
        ->and($reflection->getNumberOfRequiredParameters())
        ->toBe($requiredParams, "Messenger::{$method}() required-parameter count changed")
        ->and((string) $reflection->getReturnType())
        ->toBe($returnType, "Messenger::{$method}() return type changed");
})->with(
    collect(messengerPublicApi())
        ->map(fn (array $contract, string $method) => [$method, $contract[0], $contract[1]])
        ->values()
        ->all()
);

it('does not silently remove a public Messenger method', function () {
    $actual = collect((new ReflectionClass(Messenger::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->reject(fn (ReflectionMethod $m) => $m->isStatic() || $m->isConstructor())
        ->map(fn (ReflectionMethod $m) => $m->getName())
        ->values();

    // Every contracted method must still be present (additions are allowed).
    expect($actual->all())->toContain(...array_keys(messengerPublicApi()));
});

it('keeps the domain events that listeners and broadcasts depend on', function (string $event) {
    expect(class_exists($event))->toBeTrue("Event {$event} must exist");
})->with([
    'Syriable\Messenger\Events\MessageSent',
    'Syriable\Messenger\Events\ConversationCreated',
    'Syriable\Messenger\Events\ConversationRead',
    'Syriable\Messenger\Events\ConversationArchived',
    'Syriable\Messenger\Events\ConversationStarred',
    'Syriable\Messenger\Events\ConversationBlocked',
    'Syriable\Messenger\Events\ConversationMarkedAsSpam',
    'Syriable\Messenger\Events\ConversationCleared',
    'Syriable\Messenger\Events\MessageReported',
    'Syriable\Messenger\Events\Broadcast\MessageSentBroadcast',
    'Syriable\Messenger\Events\Broadcast\ConversationReadBroadcast',
]);

it('keeps the participant and pipe contracts stable', function () {
    expect(interface_exists('Syriable\Messenger\Contracts\MessengerParticipant'))->toBeTrue()
        ->and(interface_exists('Syriable\Messenger\Contracts\SendPipe'))->toBeTrue()
        ->and(method_exists('Syriable\Messenger\Contracts\MessengerParticipant', 'messengerParticipations'))->toBeTrue()
        ->and(method_exists('Syriable\Messenger\Contracts\SendPipe', 'handle'))->toBeTrue();
});

it('keeps the participant presenter contract stable', function (string $method) {
    expect(interface_exists('Syriable\Messenger\Contracts\ParticipantPresenter'))->toBeTrue()
        ->and(method_exists('Syriable\Messenger\Contracts\ParticipantPresenter', $method))->toBeTrue();
})->with(['displayName', 'avatarUrl', 'handle', 'profileUrl', 'timezone']);
