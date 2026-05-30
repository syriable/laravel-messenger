<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\MessageAttachment;
use Syriable\Messenger\Tests\Models\User;

beforeEach(function () {
    Storage::fake('local');
});

function sendWithAttachment(): MessageAttachment
{
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $message = Messenger::send($alice, $bob, [
        'attachments' => [UploadedFile::fake()->image('photo.jpg')],
    ]);

    return $message->attachments->first();
}

it('deletes an orphaned file but keeps referenced ones', function () {
    $attachment = sendWithAttachment();
    $directory = config('messenger.attachments.directory');

    // A stray file with no database row.
    Storage::disk('local')->put($directory.'/orphan.jpg', 'junk');

    expect(Storage::disk('local')->exists($attachment->path))->toBeTrue()
        ->and(Storage::disk('local')->exists($directory.'/orphan.jpg'))->toBeTrue();

    $pruned = Messenger::pruneAttachments();

    expect($pruned)->toHaveCount(1)
        ->and($pruned->first())->toBe($directory.'/orphan.jpg')
        ->and(Storage::disk('local')->exists($directory.'/orphan.jpg'))->toBeFalse()
        // The referenced file is untouched.
        ->and(Storage::disk('local')->exists($attachment->path))->toBeTrue();
});

it('does not delete anything on a dry run', function () {
    $directory = config('messenger.attachments.directory');
    Storage::disk('local')->put($directory.'/orphan.jpg', 'junk');

    $pruned = Messenger::pruneAttachments(dryRun: true);

    expect($pruned)->toHaveCount(1)
        ->and(Storage::disk('local')->exists($directory.'/orphan.jpg'))->toBeTrue();
});

it('returns nothing when there are no orphans', function () {
    sendWithAttachment();

    expect(Messenger::pruneAttachments())->toBeEmpty();
});

it('prunes orphaned files via the artisan command', function () {
    $directory = config('messenger.attachments.directory');
    Storage::disk('local')->put($directory.'/orphan.jpg', 'junk');

    $this->artisan('messenger:prune')
        ->expectsOutputToContain('orphan.jpg')
        ->assertSuccessful();

    expect(Storage::disk('local')->exists($directory.'/orphan.jpg'))->toBeFalse();
});

it('reports nothing to do when storage is clean', function () {
    $this->artisan('messenger:prune')
        ->expectsOutputToContain('No orphaned attachment files found.')
        ->assertSuccessful();
});

it('keeps files when the command is run with --dry-run', function () {
    $directory = config('messenger.attachments.directory');
    Storage::disk('local')->put($directory.'/orphan.jpg', 'junk');

    $this->artisan('messenger:prune --dry-run')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(Storage::disk('local')->exists($directory.'/orphan.jpg'))->toBeTrue();
});
