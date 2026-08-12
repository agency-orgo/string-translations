<?php

namespace AgencyOrgo\StringTranslations\Tests\Storage;

use AgencyOrgo\StringTranslations\Services\SettingsService;
use AgencyOrgo\StringTranslations\Tests\TestCase;
use Illuminate\Support\Facades\File;

class YamlSettingsStorageTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('string-translations.storage.driver', 'yaml');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('string-translations'));
        parent::tearDown();
    }

    public function test_the_key_round_trips_through_the_file()
    {
        $settings = app(SettingsService::class);
        $settings->set(SettingsService::DEEPL_API_KEY, 'secret:fx');

        $this->assertTrue($settings->has(SettingsService::DEEPL_API_KEY));
        $this->assertSame('secret:fx', $settings->get(SettingsService::DEEPL_API_KEY));
    }

    public function test_the_key_is_not_stored_in_plain_text()
    {
        app(SettingsService::class)->set(SettingsService::DEEPL_API_KEY, 'secret:fx');

        $this->assertStringNotContainsString(
            'secret:fx',
            File::get(storage_path('string-translations/settings.yaml'))
        );
    }

    public function test_the_directory_is_gitignored()
    {
        app(SettingsService::class)->set(SettingsService::DEEPL_API_KEY, 'secret:fx');

        $this->assertSame(
            "*\n!.gitignore\n",
            File::get(storage_path('string-translations/.gitignore'))
        );
    }
}
