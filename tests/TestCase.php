<?php

namespace ElvinQulizade\Versions\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use ElvinQulizade\Versions\Tests\Fixtures\PostResource;
use ElvinQulizade\Versions\VersionsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

class TestCase extends Orchestra
{
    use LazilyRefreshDatabase;
    use WithWorkbench;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'ElvinQulizade\\Versions\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        $providers = [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            VersionsServiceProvider::class,
        ];

        sort($providers);

        return $providers;
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('x', 32)));
        $app['config']->set('filament-versions.schedule.enabled', true);
        $app['config']->set('filament-versions.schedule.cron', '30 4 * * *');
    }

    protected function panel(): Panel
    {
        return Panel::make()
            ->id('admin')
            ->path('admin')
            ->default()
            ->resources([PostResource::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Fixtures/migrations');

        $tempMigrationsDir = sys_get_temp_dir() . '/filament-versions-test-migrations';

        if (! is_dir($tempMigrationsDir)) {
            mkdir($tempMigrationsDir, 0777, true);
        }

        // Copy each stub individually (rather than gating the whole loop on
        // one file's existence) so a newly added migration stub is picked up
        // without stale copies from a previous run hiding it.
        foreach (glob(__DIR__ . '/../database/migrations/*.php.stub') ?: [] as $stub) {
            $target = $tempMigrationsDir . '/' . basename($stub, '.stub');

            if (! is_file($target)) {
                copy($stub, $target);
            }
        }

        $this->loadMigrationsFrom($tempMigrationsDir);
    }
}
