<?php

namespace Syriable\Messenger\Actions;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Events\MessageReported;
use Syriable\Messenger\Exceptions\InvalidReportException;
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
        $this->guardLength($reason, $note);
        $this->guardMembership($message, $reporter);

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

    private function guardLength(?string $reason, ?string $note): void
    {
        $maxReason = config('messenger.reports.max_reason_length');

        if ($reason !== null && $maxReason !== null && mb_strlen($reason) > (int) $maxReason) {
            throw InvalidReportException::reasonTooLong((int) $maxReason);
        }

        $maxNote = config('messenger.reports.max_note_length');

        if ($note !== null && $maxNote !== null && mb_strlen($note) > (int) $maxNote) {
            throw InvalidReportException::noteTooLong((int) $maxNote);
        }
    }

    /**
     * When participants_only is enabled, the reporter must belong to the
     * message's conversation. Off by default — reporting is host-authorised.
     */
    private function guardMembership(Message $message, MessengerParticipant $reporter): void
    {
        if (! config('messenger.reports.participants_only', false)) {
            return;
        }

        $isParticipant = Models::participant()::query()
            ->where('conversation_id', $message->conversation_id)
            ->where('participant_type', $reporter->getMorphClass())
            ->where('participant_id', $reporter->getKey())
            ->exists();

        if (! $isParticipant) {
            throw InvalidReportException::notAParticipant();
        }
    }
}
