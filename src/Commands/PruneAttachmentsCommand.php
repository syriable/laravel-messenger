<?php

namespace Syriable\Messenger\Commands;

use Illuminate\Console\Command;
use Syriable\Messenger\Actions\PruneAttachmentsAction;

/**
 * Removes orphaned attachment files (files on disk with no matching database
 * row). Reclaims storage left behind when a host application deletes
 * messages/conversations outside the package.
 */
class PruneAttachmentsCommand extends Command
{
    protected $signature = 'messenger:prune
        {--disk= : The storage disk to scan (defaults to the configured attachments disk)}
        {--dry-run : List orphaned files without deleting them}';

    protected $description = 'Prune orphaned messenger attachment files from storage.';

    public function handle(PruneAttachmentsAction $action): int
    {
        $disk = $this->option('disk') ?: null;
        $dryRun = (bool) $this->option('dry-run');

        $orphans = $action->execute($disk, $dryRun);

        if ($orphans->isEmpty()) {
            $this->info('No orphaned attachment files found.');

            return self::SUCCESS;
        }

        $verb = $dryRun ? 'Found' : 'Deleted';
        $this->warn("{$verb} {$orphans->count()} orphaned attachment file(s):");

        $orphans->each(fn (string $path) => $this->line("  - {$path}"));

        if ($dryRun) {
            $this->comment('Dry run — no files were deleted. Re-run without --dry-run to remove them.');
        }

        return self::SUCCESS;
    }
}
