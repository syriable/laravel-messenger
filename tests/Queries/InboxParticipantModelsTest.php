<?php

use Illuminate\Support\Facades\DB;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Queries\GetInboxConversationsQuery;
use Syriable\Messenger\Tests\Models\User;

it('does not load the polymorphic participant models by default', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();

    Messenger::send($me, $a, 'hi a');
    Messenger::send($me, $b, 'hi b');

    $inbox = app(GetInboxConversationsQuery::class)->execute($me);

    $inbox->each(function (Conversation $conversation) use ($me) {
        $other = $conversation->otherParticipantFor($me);
        expect($other->relationLoaded('participant'))->toBeFalse();
    });
});

it('eager-loads participant models with one grouped query when requested', function () {
    $me = User::factory()->create();

    foreach (range(1, 5) as $i) {
        Messenger::send($me, User::factory()->create(), "hi {$i}");
    }

    $inbox = app(GetInboxConversationsQuery::class)->execute($me, [
        'with_participant_models' => true,
    ]);

    // Relation is preloaded on every participant row...
    $inbox->each(function (Conversation $conversation) {
        $conversation->participants->each(
            fn ($p) => expect($p->relationLoaded('participant'))->toBeTrue()
        );
    });

    // ...so resolving the other side's User adds zero further queries (#70).
    DB::enableQueryLog();
    $names = $inbox->map(function (Conversation $conversation) use ($me) {
        return $conversation->otherParticipantFor($me)->participant->name;
    });
    expect(DB::getQueryLog())->toBeEmpty()
        ->and($names)->toHaveCount(5);
    DB::disableQueryLog();
});
