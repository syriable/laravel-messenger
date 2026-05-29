<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Exceptions\InvalidAttachmentException;
use Syriable\Messenger\Exceptions\InvalidMessageException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\MessageAttachment;
use Syriable\Messenger\Tests\Models\User;

beforeEach(function () {
    Storage::fake('local');
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
});

/*
|--------------------------------------------------------------------------
| Count, size and type validation through the public send() entry point.
|--------------------------------------------------------------------------
*/

it('rejects a message with more attachments than the configured maximum', function () {
    config()->set('messenger.attachments.max_per_message', 2);

    Messenger::send($this->alice, $this->bob, [
        'attachments' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
        ],
    ]);
})->throws(InvalidAttachmentException::class, 'may not have more than 2');

it('accepts a message exactly at the maximum attachment count', function () {
    config()->set('messenger.attachments.max_per_message', 2);

    $message = Messenger::send($this->alice, $this->bob, [
        'attachments' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
        ],
    ]);

    expect($message->attachments)->toHaveCount(2);
});

it('rejects an attachment larger than the configured maximum size', function () {
    config()->set('messenger.attachments.max_size', 1024); // 1 MB

    Messenger::send($this->alice, $this->bob, [
        'attachments' => [UploadedFile::fake()->create('big.jpg', 20000, 'image/jpeg')],
    ]);
})->throws(InvalidAttachmentException::class, 'exceeds the maximum size');

it('accepts an attachment within the configured maximum size', function () {
    config()->set('messenger.attachments.max_size', 1024); // 1 MB

    $message = Messenger::send($this->alice, $this->bob, [
        'attachments' => [UploadedFile::fake()->create('ok.jpg', 500, 'image/jpeg')],
    ]);

    expect($message->attachments)->toHaveCount(1);
});

it('rejects an attachment with a disallowed extension and mime type', function () {
    Messenger::send($this->alice, $this->bob, [
        'attachments' => [UploadedFile::fake()->create('evil.exe', 1, 'application/x-msdownload')],
    ]);
})->throws(InvalidAttachmentException::class, 'not allowed');

it('rejects an attachment whose mime type is disallowed even with an allowed extension', function () {
    // The .jpg extension is allowed, but the (client) mime type is not.
    //
    // Note: UploadedFile::fake()->create() derives getClientMimeType() from the
    // file extension, so it cannot model an allowed-extension/disallowed-mime
    // file. We build a real test-mode UploadedFile to set the client mime
    // explicitly while keeping the allowed .jpg extension.
    $path = tempnam(sys_get_temp_dir(), 'att');
    file_put_contents($path, str_repeat('x', 16));

    $file = new UploadedFile($path, 'fake.jpg', 'application/x-msdownload', null, true);

    Messenger::send($this->alice, $this->bob, [
        'attachments' => [$file],
    ]);
})->throws(InvalidAttachmentException::class, 'not allowed');

it('accepts allowed image, pdf and zip attachments together', function () {
    $message = Messenger::send($this->alice, $this->bob, [
        'attachments' => [
            UploadedFile::fake()->image('photo.png'),
            UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('bundle.zip', 100, 'application/zip'),
        ],
    ]);

    expect($message->attachments)->toHaveCount(3);
});

it('accepts the x-zip-compressed mime variant for zip files', function () {
    $message = Messenger::send($this->alice, $this->bob, [
        'attachments' => [UploadedFile::fake()->create('bundle.zip', 100, 'application/x-zip-compressed')],
    ]);

    expect($message->attachments)->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| Message content validation (EnsureMessageHasContent) via send().
|--------------------------------------------------------------------------
*/

it('rejects a message with neither body nor attachments', function () {
    Messenger::send($this->alice, $this->bob, ['body' => null, 'attachments' => []]);
})->throws(InvalidMessageException::class);

it('accepts a body-only message', function () {
    $message = Messenger::send($this->alice, $this->bob, 'just text');

    expect($message->body)->toBe('just text')
        ->and($message->attachments)->toHaveCount(0);
});

it('accepts an attachments-only message', function () {
    $message = Messenger::send($this->alice, $this->bob, [
        'attachments' => [UploadedFile::fake()->image('photo.jpg')],
    ]);

    expect($message->body)->toBeNull()
        ->and($message->attachments)->toHaveCount(1);
});

it('accepts a message with both a body and attachments', function () {
    $message = Messenger::send($this->alice, $this->bob, [
        'body' => 'see attached',
        'attachments' => [UploadedFile::fake()->image('photo.jpg')],
    ]);

    expect($message->body)->toBe('see attached')
        ->and($message->attachments)->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| Persistence of attachment rows after a successful send.
|--------------------------------------------------------------------------
*/

it('persists a message_attachments row per file with correct metadata', function () {
    $message = Messenger::send($this->alice, $this->bob, [
        'attachments' => [
            UploadedFile::fake()->image('photo.jpg'),
            UploadedFile::fake()->create('doc.pdf', 80, 'application/pdf'),
        ],
    ]);

    expect(MessageAttachment::count())->toBe(2)
        ->and($message->attachments)->toHaveCount(2);

    $jpg = $message->attachments->firstWhere('name', 'photo.jpg');
    $pdf = $message->attachments->firstWhere('name', 'doc.pdf');

    expect($jpg)->not->toBeNull()
        ->and($jpg->disk)->toBe('local')
        ->and($jpg->mime_type)->toBe('image/jpeg')
        ->and($jpg->extension)->toBe('jpg')
        ->and($jpg->size)->toBeGreaterThan(0)
        ->and($jpg->message_id)->toBe($message->getKey());

    expect($pdf)->not->toBeNull()
        ->and($pdf->mime_type)->toBe('application/pdf')
        ->and($pdf->extension)->toBe('pdf');

    Storage::disk('local')->assertExists($jpg->path);
    Storage::disk('local')->assertExists($pdf->path);
});

it('does not persist any attachment rows when validation fails', function () {
    config()->set('messenger.attachments.max_per_message', 1);

    expect(fn () => Messenger::send($this->alice, $this->bob, [
        'attachments' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
        ],
    ]))->toThrow(InvalidAttachmentException::class);

    expect(MessageAttachment::count())->toBe(0);
});
