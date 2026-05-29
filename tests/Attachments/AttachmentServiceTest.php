<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Data\StoredAttachment;
use Syriable\Messenger\Services\AttachmentService;

beforeEach(function () {
    Storage::fake('local');
    $this->service = app(AttachmentService::class);
});

it('persists the file to the configured disk and directory', function () {
    $stored = $this->service->store(UploadedFile::fake()->image('photo.jpg'));

    expect($stored)->toBeInstanceOf(StoredAttachment::class)
        ->and($stored->disk)->toBe('local')
        ->and($stored->path)->toStartWith('messenger/attachments/');

    Storage::disk('local')->assertExists($stored->path);
});

it('honours a custom disk and directory from config', function () {
    Storage::fake('uploads');
    config()->set('messenger.attachments.disk', 'uploads');
    config()->set('messenger.attachments.directory', 'custom/files');

    $stored = app(AttachmentService::class)->store(UploadedFile::fake()->image('photo.png'));

    expect($stored->disk)->toBe('uploads')
        ->and($stored->path)->toStartWith('custom/files/');

    Storage::disk('uploads')->assertExists($stored->path);
});

it('trims surrounding slashes from the configured directory', function () {
    config()->set('messenger.attachments.directory', '/wrapped/dir/');

    $stored = app(AttachmentService::class)->store(UploadedFile::fake()->image('photo.png'));

    expect($stored->path)->toStartWith('wrapped/dir/')
        ->and($stored->path)->not->toStartWith('/');
});

it('preserves the original client filename in the name property', function () {
    $stored = $this->service->store(UploadedFile::fake()->image('holiday-snap.jpg'));

    expect($stored->name)->toBe('holiday-snap.jpg');
});

it('generates a non-original ULID-based filename that keeps the extension', function () {
    $stored = $this->service->store(UploadedFile::fake()->image('holiday-snap.jpg'));

    $generated = basename($stored->path);

    // Generated name is not the original client name...
    expect($generated)->not->toBe('holiday-snap.jpg')
        // ...but keeps the original extension.
        ->and($generated)->toEndWith('.jpg');

    // The filename stem (without extension) is a 26-char ULID.
    $stem = pathinfo($generated, PATHINFO_FILENAME);
    expect($stem)->toMatch('/^[0-9A-Za-z]{26}$/');
});

it('produces unique generated paths for repeated stores of the same file', function () {
    $first = $this->service->store(UploadedFile::fake()->image('same.jpg'));
    $second = $this->service->store(UploadedFile::fake()->image('same.jpg'));

    expect($first->path)->not->toBe($second->path);
    Storage::disk('local')->assertExists($first->path);
    Storage::disk('local')->assertExists($second->path);
});

it('captures mime type, extension and size metadata', function () {
    $file = UploadedFile::fake()->create('doc.pdf', 120, 'application/pdf');

    $stored = $this->service->store($file);

    expect($stored->mimeType)->toBe('application/pdf')
        ->and($stored->extension)->toBe('pdf')
        ->and($stored->size)->toBe($file->getSize())
        ->and($stored->size)->toBeGreaterThan(0);
});

it('exposes the metadata as database-ready attributes', function () {
    $stored = $this->service->store(UploadedFile::fake()->create('archive.zip', 50, 'application/zip'));

    $attributes = $stored->toAttributes();

    expect($attributes)->toHaveKeys(['disk', 'path', 'name', 'mime_type', 'extension', 'size'])
        ->and($attributes['disk'])->toBe($stored->disk)
        ->and($attributes['path'])->toBe($stored->path)
        ->and($attributes['name'])->toBe('archive.zip')
        ->and($attributes['mime_type'])->toBe($stored->mimeType)
        ->and($attributes['extension'])->toBe('zip')
        ->and($attributes['size'])->toBe($stored->size);
});

it('exposes the configured disk and directory accessors', function () {
    config()->set('messenger.attachments.disk', 'local');
    config()->set('messenger.attachments.directory', 'messenger/attachments');

    expect($this->service->disk())->toBe('local')
        ->and($this->service->directory())->toBe('messenger/attachments');
});
