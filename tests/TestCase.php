<?php

namespace Syriable\Messenger\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Syriable\Messenger\MessengerServiceProvider;
use Syriable\Messenger\Tests\Concerns\WithFilamentPanel;
use Syriable\Messenger\Tests\Fixtures\TestPanelProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Syriable\\Messenger\\Tests\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        $providers = array_filter([
            // Livewire ships as an optional (suggested) dependency. When present,
            // register its provider so the bundled UI components can be tested;
            // testbench does not auto-discover dependency providers.
            class_exists(LivewireServiceProvider::class)
                ? LivewireServiceProvider::class
                : null,
            MessengerServiceProvider::class,
        ]);

        // Only the Filament tests (which opt in via the WithFilamentPanel trait)
        // pay for Filament's providers + the test panel.
        if (in_array(WithFilamentPanel::class, class_uses_recursive($this), true)) {
            $providers = array_merge($providers, [
                BladeIconsServiceProvider::class,
                BladeHeroiconsServiceProvider::class,
                SupportServiceProvider::class,
                ActionsServiceProvider::class,
                FormsServiceProvider::class,
                TablesServiceProvider::class,
                SchemasServiceProvider::class,
                InfolistsServiceProvider::class,
                NotificationsServiceProvider::class,
                WidgetsServiceProvider::class,
                FilamentServiceProvider::class,
                TestPanelProvider::class,
            ]);
        }

        return $providers;
    }

    public function getEnvironmentSetUp($app)
    {
        // Livewire encrypts component checksums, which requires an app key.
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Default to in-memory SQLite for fast local runs, but allow CI to point
        // the suite at MySQL or PostgreSQL via DB_CONNECTION so the package's
        // concurrency / locking paths are exercised on real databases (#69).
        $connection = env('DB_CONNECTION', 'sqlite');

        config()->set('database.default', $connection);

        if ($connection === 'sqlite') {
            config()->set('database.connections.sqlite', [
                'driver' => 'sqlite',
                'database' => env('DB_DATABASE', ':memory:'),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);

            return;
        }

        config()->set("database.connections.{$connection}", [
            'driver' => $connection,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', $connection === 'pgsql' ? '5432' : '3306'),
            'database' => env('DB_DATABASE', 'messenger_test'),
            'username' => env('DB_USERNAME', $connection === 'pgsql' ? 'postgres' : 'root'),
            'password' => env('DB_PASSWORD', ''),
            'prefix' => '',
            'charset' => $connection === 'pgsql' ? 'utf8' : 'utf8mb4',
        ]);
    }

    /**
     * Build the test participant table and run the package migrations. The
     * package ships its migrations as publishable stubs, so we run them
     * explicitly here against the configured database. Tables are dropped first
     * so the suite is idempotent on persistent drivers (MySQL/PostgreSQL).
     */
    protected function setUpDatabase(): void
    {
        $this->dropMessengerTables();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        // A second, distinct morph participant type for cross-model coverage.
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
     * Drop all package + harness tables in dependency order. No-op on a fresh
     * database; required for idempotent re-runs on MySQL/PostgreSQL.
     */
    protected function dropMessengerTables(): void
    {
        foreach ([
            'messenger_saved_messages',
            'messenger_message_reports',
            'messenger_message_attachments',
            'messenger_messages',
            'messenger_participants',
            'messenger_conversations',
            'agents',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
