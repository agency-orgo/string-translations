<?php

namespace AgencyOrgo\StringTranslations\Tests\GraphQL;

use AgencyOrgo\StringTranslations\Models\LocalizedString;
use AgencyOrgo\StringTranslations\Tests\TestCase;
use Statamic\Facades\Site;

class CreateStringTranslationsMutationTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.graphql.enabled', true);
        $app['config']->set('statamic.editions.pro', true);
    }

    public function test_it_creates_keys_across_all_sites()
    {
        $sites = Site::all()->keys()->all();

        $response = $this->postJson('/graphql', [
            'query' => 'mutation { createStringTranslations(keys: ["test.greeting"]) { created } }',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.createStringTranslations.created', count($sites));

        foreach ($sites as $site) {
            $this->assertDatabaseHas('localized_strings', [
                'key' => 'test.greeting',
                'lang' => $site,
                'value' => 'untranslated_test.greeting',
            ]);
        }
    }

    public function test_it_creates_multiple_keys()
    {
        $sites = Site::all()->keys()->all();

        $response = $this->postJson('/graphql', [
            'query' => 'mutation { createStringTranslations(keys: ["key.one", "key.two"]) { created } }',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.createStringTranslations.created', 2 * count($sites));
    }

    public function test_it_ignores_duplicate_keys()
    {
        $sites = Site::all()->keys()->all();

        $this->postJson('/graphql', [
            'query' => 'mutation { createStringTranslations(keys: ["existing.key"]) { created } }',
        ]);

        $response = $this->postJson('/graphql', [
            'query' => 'mutation { createStringTranslations(keys: ["existing.key"]) { created } }',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.createStringTranslations.created', 0);

        foreach ($sites as $site) {
            $this->assertEquals(1, LocalizedString::where('key', 'existing.key')->where('lang', $site)->count());
        }
    }

    public function test_keys_argument_is_required()
    {
        $response = $this->postJson('/graphql', [
            'query' => 'mutation { createStringTranslations { created } }',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data', null);
        $this->assertNotEmpty($response->json('errors'));
    }

    public function test_created_keys_are_readable_via_query()
    {
        $this->postJson('/graphql', [
            'query' => 'mutation { createStringTranslations(keys: ["nav.about"]) { created } }',
        ]);

        $site = Site::all()->keys()->first();

        $response = $this->postJson('/graphql', [
            'query' => '{ stringTranslations(lang: "'.$site.'") { lang strings } }',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.stringTranslations.lang', $site);

        $strings = $response->json('data.stringTranslations.strings');
        $this->assertArrayHasKey('nav.about', $strings);
        $this->assertEquals('untranslated_nav.about', $strings['nav.about']);
    }
}
