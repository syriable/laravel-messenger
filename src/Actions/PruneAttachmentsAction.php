<?php

namespace Syriable\Messenger\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Services\AttachmentService;
use Syriable\Messenger\Support\Models;

/**
 * Garbage-collects orphaned attachment files: files that live under the
 * configured attachments directory on disk but no longer have a matching
 * `messenger_message_attachments` row.
 *
 * These accumulate when a host application deletes messages/conversations
 * outside the package (the package itself never hard-deletes), since messages
 * are immutable and clearing is only a visibility reset. Pruning is explicit
 * and opt-in — it never runs automatically — so it is safe against the
 * immutability model.
 */
class PruneAttachmentsAction
{
    public function __construct(
        private readonly AttachmentService $attachments,
    ) {}

    /**
     * @param  string|null  $disk  Disk to scan; defaults to the configured attachments disk.
     * @param  bool  $dryRun  When true, identify orphans without deleting them.
     * @return Collection<int, string> The orphaned file paths (deleted unless dry-run).
     */
    public function execute(?string $disk = null, bool $dryRun = false): Collection
    {
        $disk ??= $this->attachments->disk();
        $directory = $this->attachments->directory();

        $filesOnDisk = collect(Storage::disk($disk)->allFiles($directory));

        if ($filesOnDisk->isEmpty()) {
            return collect();
        }

        // Stream known paths for this disk in chunks and build a lookup set,
        // instead of pulling the entire `path` column into memory in one go, so
        // the prune scales to large attachment tables (#audit P3).
        $knownPaths = collect();

        Models::attachment()::query()
            ->where('disk', $disk)
            ->select('path')
            ->chunk(1000, function ($rows) use ($knownPaths): void {
                foreach ($rows as $row) {
                    $knownPaths->put($row->path, true);
                }
            });

        $orphans = $filesOnDisk->reject(fn (string $path) => $knownPaths->has($path))->values();

        if (! $dryRun) {
            $orphans->each(fn (string $path) => Storage::disk($disk)->delete($path));
        }

        return $orphans;
    }
}
