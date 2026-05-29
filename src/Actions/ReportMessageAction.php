<?php

namespace Syriable\Messenger\Actions;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Events\MessageReported;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Models\MessageReport;
use Syriable\Messenger\Support\Models;

/**
 * Reports a specific message. Reporting is message-based, never
 * conversation-based. A reporter may report a given message only once; a
 * repeated report updates the existing one rather than creating a duplicate.
 */
class ReportMessageAction
{
    public function execute(
        Message $message,
        MessengerParticipant $reporter,
        ?string $reason = null,
        ?string $note = null,
    ): MessageReport {
        /** @var MessageReport $report */
        $report = Models::report()::query()->updateOrCreate(
            [
                'message_id' => $message->getKey(),
                'reporter_type' => $reporter->getMorphClass(),
                'reporter_id' => $reporter->getKey(),
            ],
            [
                'reason' => $reason,
                'note' => $note,
            ],
        );

        MessageReported::dispatch($report);

        return $report;
    }
}
