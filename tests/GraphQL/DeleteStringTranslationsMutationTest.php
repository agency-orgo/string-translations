<?php

namespace AgencyOrgo\StringTranslations\Tests\GraphQL;

use AgencyOrgo\StringTranslations\Contracts\TranslationRepository;
use AgencyOrgo\StringTranslations\Events\TranslationsDeleted;
use AgencyOrgo\StringTranslations\Models\LocalizedString;
use AgencyOrgo\StringTranslations\Services\TranslationService;
use AgencyOrgo\StringTranslations\Support\YamlTranslationStore;
use AgencyOrgo\StringTranslations\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class DeleteStringTranslationsMutationTest extends TestCase
{
    private const TOKEN = 'test-delete-token';

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.graphql.enabled', true);
        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('string-translations.graphql.delete_token', self::TOKEN);
    }

    public function test_it_deletes_keys_from_every_locale()
    {
        LocalizedString::create(['key' => 'doomed', 'lang' => 'en', 'value' => 'Doomed']);
        LocalizedString::create(['key' => 'doomed', 'lang' => 'es', 'value' => 'Condenado']);
        LocalizedString::create(['key' => 'kept', 'lang' => 'en', 'value' => 'Kept']);

        $response = $this->deleteKeys(['doomed']);

        $response->assertOk();
        $response->assertJsonPath('data.deleteStringTranslations.deleted', ['doomed']);
        $this->assertDatabaseMissing('localized_strings', ['key' => 'doomed']);
        $this->assertDatabaseHas('localized_strings', ['key' => 'kept', 'lang' => 'en']);
    }

    public function test_it_returns_only_keys_that_existed()
    {
        LocalizedString::create(['key' => 'existing', 'lang' => 'en', 'value' => 'Existing']);

        $response = $this->deleteKeys(['existing', 'missing', 'existing']);

        $response->assertOk();
        $response->assertJsonPath('data.deleteStringTranslations.deleted', ['existing']);
    }

    public function test_it_dispatches_translations_deleted_with_the_removed_keys()
    {
        LocalizedString::create(['key' => 'one', 'lang' => 'en', 'value' => 'One']);
        LocalizedString::create(['key' => 'two', 'lang' => 'en', 'value' => 'Two']);

        Event::fake([TranslationsDeleted::class]);

        $this->deleteKeys(['one', 'two', 'missing'])->assertOk();

        Event::assertDispatched(TranslationsDeleted::class, function ($event) {
            return $event->keys === ['one', 'two'];
        });
    }

    public function test_unknown_keys_delete_nothing_and_dispatch_no_event()
    {
        LocalizedString::create(['key' => 'kept', 'lang' => 'en', 'value' => 'Kept']);

        Event::fake([TranslationsDeleted::class]);

        $response = $this->deleteKeys(['missing']);

        $response->assertOk();
        $response->assertJsonPath('data.deleteStringTranslations.deleted', []);
        $this->assertDatabaseCount('localized_strings', 1);
        Event::assertNotDispatched(TranslationsDeleted::class);
    }

    public function test_it_is_refused_when_no_delete_token_is_configured()
    {
        config(['string-translations.graphql.delete_token' => null]);
        LocalizedString::create(['key' => 'kept', 'lang' => 'en', 'value' => 'Kept']);

        $response = $this->deleteKeys(['kept']);

        $response->assertJsonPath('data.deleteStringTranslations', null);
        $response->assertJsonPath('errors.0.extensions.category', 'authorization');
        $this->assertDatabaseHas('localized_strings', ['key' => 'kept']);
    }

    public function test_it_is_refused_when_the_token_header_is_missing()
    {
        LocalizedString::create(['key' => 'kept', 'lang' => 'en', 'value' => 'Kept']);

        $response = $this->deleteKeys(['kept'], token: null);

        $response->assertJsonPath('data.deleteStringTranslations', null);
        $response->assertJsonPath('errors.0.extensions.category', 'authorization');
        $this->assertDatabaseHas('localized_strings', ['key' => 'kept']);
    }

    public function test_it_is_refused_when_the_token_does_not_match()
    {
        LocalizedString::create(['key' => 'kept', 'lang' => 'en', 'value' => 'Kept']);

        $response = $this->deleteKeys(['kept'], token: 'wrong-token');

        $response->assertJsonPath('data.deleteStringTranslations', null);
        $response->assertJsonPath('errors.0.extensions.category', 'authorization');
        $this->assertDatabaseHas('localized_strings', ['key' => 'kept']);
    }

    public function test_it_trims_keys_like_the_cp_save_does()
    {
        LocalizedString::create(['key' => 'nav.contact', 'lang' => 'en', 'value' => 'Contact']);

        $response = $this->deleteKeys([' nav.contact ']);

        $response->assertOk();
        $response->assertJsonPath('data.deleteStringTranslations.deleted', ['nav.contact']);
        $this->assertDatabaseMissing('localized_strings', ['key' => 'nav.contact']);
    }

    public function test_keys_argument_is_required()
    {
        $response = $this->postJson('/graphql', [
            'query' => 'mutation { deleteStringTranslations { deleted } }',
        ], ['X-String-Translations-Token' => self::TOKEN]);

        $response->assertOk();
        $response->assertJsonPath('data', null);
        $this->assertNotEmpty($response->json('errors'));
    }

    public function test_it_deletes_from_yaml_files_and_the_meta_sidecar()
    {
        $path = storage_path('framework/testing/st-translations-delete');
        File::deleteDirectory($path);

        config(['string-translations.storage.driver' => 'yaml']);
        $this->app->forgetInstance(TranslationRepository::class);
        $this->app->singleton(YamlTranslationStore::class, fn () => new YamlTranslationStore($path));

        try {
            TranslationService::saveToDatabase('en', ['doomed' => 'Doomed', 'kept' => 'Kept']);
            TranslationService::bulkUpsertAutoTranslations('et', ['doomed' => 'Hukule määratud']);

            $response = $this->deleteKeys(['doomed']);

            $response->assertOk();
            $response->assertJsonPath('data.deleteStringTranslations.deleted', ['doomed']);
            $this->assertSame(['kept' => 'Kept'], Yaml::parseFile($path.'/en.yaml'));
            $this->assertEmpty(Yaml::parseFile($path.'/et.yaml'));
            $this->assertFileDoesNotExist($path.'/.meta.yaml');
        } finally {
            File::deleteDirectory($path);
        }
    }

    private function deleteKeys(array $keys, ?string $token = self::TOKEN)
    {
        $headers = $token === null ? [] : ['X-String-Translations-Token' => $token];

        return $this->postJson('/graphql', [
            'query' => 'mutation ($keys: [String!]!) { deleteStringTranslations(keys: $keys) { deleted } }',
            'variables' => ['keys' => $keys],
        ], $headers);
    }
}
