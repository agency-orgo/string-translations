<?php

namespace AgencyOrgo\StringTranslations\Tests;

use AgencyOrgo\StringTranslations\ServiceProvider;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends AddonTestCase
{
    use RefreshDatabase;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure database for testing
        $this->app['config']->set('database.default', 'testing');
        $this->app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Load addon config
        $this->app['config']->set('string-translations.database.connection', 'testing');
        $this->app['config']->set('string-translations.database.table', 'localized_strings');

        // Run migrations manually since RefreshDatabase doesn't work with addons
        $this->runMigrations();

        // Persist a Statamic super user for CP routes and authorize properly
        User::all()->each->delete();

        $user = User::make()
            ->email('test@example.com')
            ->makeSuper();

        $user->save();

        $this->actingAs($user);
    }

    protected function runMigrations(): void
    {
        $migrationPath = __DIR__ . '/../migrations';

        if (is_dir($migrationPath)) {
            $files = glob($migrationPath . '/*.php');

            foreach ($files as $file) {
                $migration = require $file;
                $migration->up();
            }
        }
    }
}