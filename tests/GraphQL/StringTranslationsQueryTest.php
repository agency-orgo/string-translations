<?php

namespace AgencyOrgo\StringTranslations\Tests\GraphQL;

use AgencyOrgo\StringTranslations\Models\LocalizedString;
use AgencyOrgo\StringTranslations\Tests\TestCase;

class StringTranslationsQueryTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.graphql.enabled', true);
        $app['config']->set('statamic.editions.pro', true);
    }

    public function test_it_returns_translations_for_a_language()
    {
        LocalizedString::create(['key' => 'nav.home', 'lang' => 'en', 'value' => 'Home']);
        LocalizedString::create(['key' => 'welcome.message', 'lang' => 'en', 'value' => 'Welcome!']);

        $response = $this->postJson('/graphql', [
            'query' => '{ string_translations(lang: "en") { lang strings } }',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.string_translations.lang', 'en');
        $response->assertJsonPath('data.string_translations.strings', [
            'nav.home' => 'Home',
            'welcome.message' => 'Welcome!',
        ]);
    }

    public function test_it_returns_empty_strings_for_unknown_language()
    {
        LocalizedString::create(['key' => 'nav.home', 'lang' => 'en', 'value' => 'Home']);

        $response = $this->postJson('/graphql', [
            'query' => '{ string_translations(lang: "fr") { lang strings } }',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.string_translations.lang', 'fr');
        $response->assertJsonPath('data.string_translations.strings', []);
    }

    public function test_lang_argument_is_required()
    {
        $response = $this->postJson('/graphql', [
            'query' => '{ string_translations { lang strings } }',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data', null);
        $this->assertNotEmpty($response->json('errors'));
    }

    public function test_strings_are_ordered_by_key()
    {
        LocalizedString::create(['key' => 'zebra', 'lang' => 'en', 'value' => 'Zebra']);
        LocalizedString::create(['key' => 'apple', 'lang' => 'en', 'value' => 'Apple']);
        LocalizedString::create(['key' => 'mango', 'lang' => 'en', 'value' => 'Mango']);

        $response = $this->postJson('/graphql', [
            'query' => '{ string_translations(lang: "en") { strings } }',
        ]);

        $response->assertOk();

        $keys = array_keys($response->json('data.string_translations.strings'));
        $this->assertEquals(['apple', 'mango', 'zebra'], $keys);
    }

    public function test_it_only_returns_translations_for_requested_language()
    {
        LocalizedString::create(['key' => 'nav.home', 'lang' => 'en', 'value' => 'Home']);
        LocalizedString::create(['key' => 'nav.home', 'lang' => 'de', 'value' => 'Startseite']);

        $response = $this->postJson('/graphql', [
            'query' => '{ string_translations(lang: "de") { lang strings } }',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.string_translations.lang', 'de');
        $response->assertJsonPath('data.string_translations.strings', [
            'nav.home' => 'Startseite',
        ]);
    }
}
