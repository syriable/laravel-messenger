<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Contracts\SendPipe;
use Syriable\Messenger\Data\NewMessage;
use Syriable\Messenger\Data\PendingMessage;
use Syriable\Messenger\Exceptions\InvalidAttachmentException;
use Syriable\Messenger\Exceptions\InvalidMessageException;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Exceptions\MessengerException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Pipelines\Send\EnsureAttachmentsAreValid;
use Syriable\Messenger\Pipelines\Send\EnsureMessageHasContent;
use Syriable\Messenger\Pipelines\Send\EnsureParticipantsAreValid;
use Syriable\Messenger\Tests\Models\User;

/**
 * A custom inline pipe used to prove the pipeline is fully configurable: host
 * apps may plug in their own moderation/validation stages via config.
 */
class RejectBannedWordPipe implements SendPipe
{
    public function handle(PendingMessage $message, Closure $next): PendingMessage
    {
        if (str_contains(strtolower((string) $message->message->body), 'banned')) {
            throw new RejectedByCustomPipeException('Message contains a banned word.');
        }

        return $next($message);
    }
}

class RejectedByCustomPipeException extends MessengerException {}

/**
 * Records the order in which it ran so we can assert pipe ordering.
 */
class RecordingPipe implements SendPipe
{
    public static array $log = [];

    public function __construct(private readonly string $label = 'recording') {}

    public function handle(PendingMessage $message, Closure $next): PendingMessage
    {
        self::$log[] = $this->label;

        return $next($message);
    }
}

beforeEach(function () {
    Storage::fake('local');
    RecordingPipe::$log = [];
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
});

/*
|--------------------------------------------------------------------------
| Driving the configured pipeline directly via Illuminate's Pipeline.
|--------------------------------------------------------------------------
*/

function runPipeline(PendingMessage $message): PendingMessage
{
    return app(Pipeline::class)
        ->send($message)
        ->through(config('messenger.pipeline', []))
        ->via('handle')
        ->thenReturn();
}

it('runs the configured pipes in order', function () {
    config()->set('messenger.pipeline', [
        new RecordingPipe('first'),
        new RecordingPipe('second'),
        new RecordingPipe('third'),
    ]);

    $message = new PendingMessage($this->alice, $this->bob, new NewMessage('hi'));

    runPipeline($message);

    expect(RecordingPipe::$log)->toBe(['first', 'second', 'third']);
});

it('supports plugging in a custom moderation pipe through config', function () {
    config()->set('messenger.pipeline', [
        EnsureParticipantsAreValid::class,
        RejectBannedWordPipe::class,
        EnsureMessageHasContent::class,
    ]);

    $blocked = new PendingMessage($this->alice, $this->bob, new NewMessage('this is banned'));

    expect(fn () => runPipeline($blocked))->toThrow(RejectedByCustomPipeException::class);

    // A clean message still passes the same custom pipeline.
    $clean = new PendingMessage($this->alice, $this->bob, new NewMessage('this is fine'));
    expect(runPipeline($clean))->toBe($clean);
});

it('only runs the pipes present in config, so removing a pipe skips its check', function () {
    // Drop EnsureMessageHasContent: an empty message now passes the pipeline.
    config()->set('messenger.pipeline', [
        EnsureParticipantsAreValid::class,
    ]);

    $empty = new PendingMessage($this->alice, $this->bob, new NewMessage(null, []));

    expect(runPipeline($empty))->toBe($empty);
});

it('a custom pipe is enforced end-to-end through Messenger::send', function () {
    config()->set('messenger.pipeline', [
        EnsureParticipantsAreValid::class,
        RejectBannedWordPipe::class,
        EnsureMessageHasContent::class,
        EnsureAttachmentsAreValid::class,
    ]);

    expect(fn () => Messenger::send($this->alice, $this->bob, 'this is banned'))
        ->toThrow(RejectedByCustomPipeException::class);

    $message = Messenger::send($this->alice, $this->bob, 'perfectly fine');
    expect($message->body)->toBe('perfectly fine');
});

it('reordering pipes changes which validation error surfaces first', function () {
    // Sender == recipient AND empty message. Whichever pipe is first wins.
    $bad = fn () => new PendingMessage($this->alice, $this->alice, new NewMessage(null, []));

    config()->set('messenger.pipeline', [
        EnsureMessageHasContent::class,
        EnsureParticipantsAreValid::class,
    ]);
    expect(fn () => runPipeline($bad()))->toThrow(InvalidMessageException::class);

    config()->set('messenger.pipeline', [
        EnsureParticipantsAreValid::class,
        EnsureMessageHasContent::class,
    ]);
    expect(fn () => runPipeline($bad()))
        ->toThrow(InvalidParticipantException::class);
});

it('passes a fully valid message through the default pipeline unchanged', function () {
    $message = new PendingMessage($this->alice, $this->bob, new NewMessage('hello', [
        UploadedFile::fake()->image('a.jpg'),
    ]));

    expect(runPipeline($message))->toBe($message);
});

it('still enforces attachment limits when running the default configured pipeline', function () {
    config()->set('messenger.attachments.max_per_message', 1);

    $message = new PendingMessage($this->alice, $this->bob, new NewMessage(null, [
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->image('b.jpg'),
    ]));

    expect(fn () => runPipeline($message))->toThrow(InvalidAttachmentException::class);
});
