<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('contracts are interfaces')
    ->expect('Syriable\Messenger\Contracts')
    ->toBeInterfaces();

arch('models are thin Eloquent models')
    ->expect('Syriable\Messenger\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('send pipes implement the SendPipe contract')
    ->expect('Syriable\Messenger\Pipelines\Send')
    ->toImplement('Syriable\Messenger\Contracts\SendPipe');

arch('queries are read-only and never dispatch domain events')
    ->expect('Syriable\Messenger\Queries')
    ->not->toUse('Syriable\Messenger\Events');

arch('domain events do not depend on actions')
    ->expect('Syriable\Messenger\Events')
    ->not->toUse('Syriable\Messenger\Actions');

arch('actions and queries do not depend on each other writing through the other layer')
    ->expect('Syriable\Messenger\Queries')
    ->not->toUse('Syriable\Messenger\Actions');
