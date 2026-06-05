<?php

namespace Syriable\Messenger\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Syriable\Messenger\Support\Models;

/**
 * Convenience installer that publishes the package's migration stubs (and,
 * optionally, the config) and guides the integrator through the required
 * publish-then-migrate workflow.
 *
 * The package ships migrations as `.php.stub` files that Laravel's migrator
 * cannot run directly, so publishing them is mandatory. This command makes that
 * step discoverable instead of relying on the README alone (#audit High-4).
 */
class InstallCommand extends Command
{
    protected $signature = 'messenger:install
        {--config : Also publish the configuration file}
        {--migrate : Run the migrations after publishing them}
        {--force : Overwrite any existing published files}';

    protected $description = 'Publish the messenger migrations (and optionally config), then migrate.';

    public function handle(): int
    {
        $this->components->info('Publishing messenger migrations…');

        $this->callSilently('vendor:publish', [
            '--tag' => 'messenger-migrations',
            '--force' => (bool) $this->option('force'),
        ]);

        if ($this->option('config')) {
            $this->components->info('Publishing messenger config…');

            $this->callSilently('vendor:publish', [
                '--tag' => 'messenger-config',
                '--force' => (bool) $this->option('force'),
            ]);
        }

        if ($this->option('migrate')) {
            $this->components->info('Running migrations…');

            $this->call('migrate');
        } else {
            $this->components->warn('Run "php artisan migrate" to create the messenger tables.');
        }

        return self::SUCCESS;
    }

    /**
     * Whether the core messenger tables already exist. Useful for host apps that
     * want to detect a missing-migration state at boot.
     */
    public static function tablesExist(): bool
    {
        return Schema::hasTable(Models::newConversation()->getTable())
            && Schema::hasTable(Models::newParticipant()->getTable())
            && Schema::hasTable(Models::newMessage()->getTable());
    }
}
