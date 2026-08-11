<?php

namespace AgencyOrgo\StringTranslations\Tests\Storage;

use AgencyOrgo\StringTranslations\Contracts\TranslationRepository;
use AgencyOrgo\StringTranslations\Events\TranslationsSaved;
use AgencyOrgo\StringTranslations\Services\TranslationService;
use AgencyOrgo\StringTranslations\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;

class YamlGitDisabledTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('string-translations.storage.driver', 'yaml');
        $app['config']->set('string-translations.storage.path', storage_path('framework/testing/st-git-off'));
        $app['config']->set('statamic.git.enabled', false);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/st-git-off'));
        parent::tearDown();
    }

    public function test_storage_path_is_still_tracked()
    {
        $this->assertContains(
            storage_path('framework/testing/st-git-off'),
            config('statamic.git.paths')
        );
    }

    public function test_no_git_listeners_are_registered()
    {
        $this->assertEmpty(
            array_filter(
                Event::getRawListeners()[TranslationsSaved::class] ?? [],
                fn ($listener) => is_string($listener) && str_contains($listener, 'Git')
            )
        );
    }

    public function test_saving_works()
    {
        TranslationService::saveToDatabase('en', ['hello' => 'Hello']);

        $this->assertSame(
            'Hello',
            app(TranslationRepository::class)->forLang('en')['hello']['value']
        );
    }
}
