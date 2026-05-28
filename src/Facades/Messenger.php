<?php

namespace Syriable\Messenger\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Syriable\Messenger\Messenger
 */
class Messenger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Syriable\Messenger\Messenger::class;
    }
}
