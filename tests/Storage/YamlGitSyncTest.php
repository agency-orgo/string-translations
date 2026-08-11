<?php

namespace AgencyOrgo\StringTranslations\Tests\Storage;

use AgencyOrgo\StringTranslations\Services\TranslationService;
use AgencyOrgo\StringTranslations\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Git;

class YamlGitSyncTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('string-translations.storage.driver', 'yaml');
        $app['config']->set('string-translations.storage.path', storage_path('framework/testing/st-git'));
        $app['config']->set('statamic.git.enabled', true);
        $app['config']->set('statamic.git.automatic', true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/st-git'));
        parent::tearDown();
    }

    public function test_storage_path_is_tracked_by_statamic_git()
    {
        $this->assertContains(
            storage_path('framework/testing/st-git'),
            config('statamic.git.paths')
        );
    }

    public function test_saving_dispatches_a_git_commit()
    {
        Git::shouldReceive('as')->andReturnSelf();
        Git::shouldReceive('dispatchCommit')->once()->with('Translations saved: en');

        TranslationService::saveToDatabase('en', ['hello' => 'Hello']);
    }

    public function test_deleting_dispatches_a_git_commit()
    {
        TranslationService::saveToDatabase('en', ['doomed' => 'x']);

        Git::shouldReceive('as')->andReturnSelf();
        Git::shouldReceive('dispatchCommit')->once()->with('Translations deleted');

        TranslationService::saveToDatabase('en', [], ['doomed']);
    }

    public function test_nothing_is_committed_when_automatic_git_is_off()
    {
        config(['statamic.git.automatic' => false]);

        Git::shouldReceive('dispatchCommit')->never();

        TranslationService::saveToDatabase('en', ['hello' => 'Hello']);
    }
}
