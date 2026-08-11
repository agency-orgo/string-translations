<?php

namespace AgencyOrgo\StringTranslations\Tests\Storage;

use AgencyOrgo\StringTranslations\Contracts\TranslationRepository;
use AgencyOrgo\StringTranslations\Repositories\YamlTranslationRepository;
use AgencyOrgo\StringTranslations\Services\TranslationService;
use AgencyOrgo\StringTranslations\Support\YamlTranslationStore;
use AgencyOrgo\StringTranslations\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class YamlTranslationDriverTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/st-translations');
        File::deleteDirectory($this->path);

        config(['string-translations.storage.driver' => 'yaml']);
        $this->app->forgetInstance(YamlTranslationStore::class);
        $this->app->forgetInstance(TranslationRepository::class);
        $this->app->singleton(YamlTranslationStore::class, fn () => new YamlTranslationStore($this->path));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);
        parent::tearDown();
    }

    public function test_driver_is_yaml()
    {
        $this->assertInstanceOf(YamlTranslationRepository::class, app(TranslationRepository::class));
    }

    public function test_save_writes_yaml_and_not_the_database()
    {
        TranslationService::saveToDatabase('en', ['welcome.message' => 'Welcome!']);

        $this->assertFileExists($this->path.'/en.yaml');
        $this->assertSame(
            ['welcome.message' => 'Welcome!'],
            Yaml::parseFile($this->path.'/en.yaml')
        );

        $this->assertDatabaseCount('localized_strings', 0);
    }

    public function test_auto_translated_flag_round_trips_via_meta_sidecar()
    {
        TranslationService::bulkUpsertAutoTranslations('et', ['hello' => 'Tere']);

        $this->assertSame(
            ['et' => ['hello' => true]],
            Yaml::parseFile($this->path.'/.meta.yaml')
        );

        $repo = app(TranslationRepository::class);
        $this->assertTrue($repo->forLang('et')['hello']['auto']);

        TranslationService::saveToDatabase('et', ['hello' => 'Tere maailm']);
        $this->assertFalse($repo->forLang('et')['hello']['auto']);
        $this->assertFileDoesNotExist($this->path.'/.meta.yaml');
    }

    public function test_delete_removes_keys_from_every_locale()
    {
        TranslationService::saveToDatabase('en', ['keep' => 'Keep', 'drop' => 'Drop']);
        TranslationService::saveToDatabase('et', ['keep' => 'Hoia', 'drop' => 'Kustuta']);

        TranslationService::saveToDatabase('en', [], ['drop']);

        $repo = app(TranslationRepository::class);
        $this->assertArrayNotHasKey('drop', $repo->forLang('en'));
        $this->assertArrayNotHasKey('drop', $repo->forLang('et'));
        $this->assertArrayHasKey('keep', $repo->forLang('et'));
    }

    public function test_unchanged_save_does_not_rewrite_the_file()
    {
        TranslationService::saveToDatabase('en', ['a' => 'A']);
        $mtime = filemtime($this->path.'/en.yaml');

        clearstatcache();
        TranslationService::saveToDatabase('en', ['a' => 'A']);

        $this->assertSame($mtime, filemtime($this->path.'/en.yaml'));
    }
}
