<?php

namespace Syriable\Messenger\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Syriable\Messenger\Models\MessageReport;

class MessageReported
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly MessageReport $report,
    ) {}
}
