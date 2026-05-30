<?php

/**
 * Shared bootstrap for the standalone QA harnesses.
 *
 * Boots a real Laravel application via Orchestra Testbench (the same way a
 * consuming app would run), registers the Messenger service provider, points
 * the database at a file-backed SQLite connection (so multiple processes can
 * share it for the parallel-stress harness), and runs the package migrations
 * plus the test participant tables.
 *
 * Returns the booted application instance.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\Foundation\Application as Testbench;
use Syriable\Messenger\MessengerServiceProvider;

require __DIR__.'/../vendor/autoload.php';

/**
 * @param  string  $database  Absolute path to the SQLite database file.
 * @param  bool  $fresh  Recreate the schema from scratch.
 */
function messenger_qa_boot(string $database, bool $fresh = true): Application
{
    if ($fresh && file_exists($database)) {
        @unlink($database);
    }

    touch($database);

    /** @var Application $app */
    $app = Testbench::create(
        basePath: realpath(__DIR__.'/..').'/vendor/orchestra/testbench-core/laravel',
        options: ['enables_package_discoveries' => false],
    );

    $app->register(MessengerServiceProvider::class);

    $app['config']->set('database.default', 'qa');
    $app['config']->set('database.connections.qa', [
        'driver' => 'sqlite',
        'database' => $database,
        'prefix' => '',
        'foreign_key_constraints' => true,
        // WAL + a generous busy timeout let multiple worker processes share the
        // file without immediate "database is locked" failures.
        'journal_mode' => 'WAL',
        'busy_timeout' => 5000,
    ]);

    if ($fresh) {
        messenger_qa_migrate($app);
    }

    return $app;
}

function messenger_qa_migrate(Application $app): void
{
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    Schema::create('agents', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    $migrations = collect(File::files(__DIR__.'/../database/migrations'))
        ->sortBy(fn ($file) => $file->getFilename());

    foreach ($migrations as $migration) {
        (include $migration->getRealPath())->up();
    }
}

/**
 * Tiny assertion helper — prints PASS/FAIL and tracks failures via a global.
 */
function qa_assert(string $label, bool $condition): void
{
    $GLOBALS['qa_failures'] ??= 0;

    if ($condition) {
        echo "  \033[32mPASS\033[0m  {$label}\n";
    } else {
        $GLOBALS['qa_failures']++;
        echo "  \033[31mFAIL\033[0m  {$label}\n";
    }
}

function qa_summary(): int
{
    $failures = $GLOBALS['qa_failures'] ?? 0;

    echo "\n".($failures === 0
        ? "\033[32mAll checks passed.\033[0m\n"
        : "\033[31m{$failures} check(s) failed.\033[0m\n");

    return $failures === 0 ? 0 : 1;
}
