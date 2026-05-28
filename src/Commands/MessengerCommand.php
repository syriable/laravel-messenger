<?php

namespace Syriable\Messenger\Commands;

use Illuminate\Console\Command;

class MessengerCommand extends Command
{
    public $signature = 'laravel-messenger';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
