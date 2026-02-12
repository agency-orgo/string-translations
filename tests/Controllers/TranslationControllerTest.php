<?php

namespace AgencyOrgo\StringTranslations\Tests\Controllers;

use AgencyOrgo\StringTranslations\Models\LocalizedString;
use AgencyOrgo\StringTranslations\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

class TranslationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_inertia_page_with_data()
    {
        LocalizedString::create([
            'key' => 'welcome.message',
            'lang' => 'en',
            'value' => 'Welcome!'
        ]);

        $response = $this->get(cp_route('utilities.string-translations'));

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('string-translations::StringTranslations')
            ->has('translations', 1)
            ->has('activeLang')
            ->has('sites')
            ->has('saveUrl')
            ->where('missingTable', false)
        );
    }

    public function test_make_saves_translations()
    {
        $data = [
            'lang' => 'en',
            'strings' => [
                'welcome.message' => 'Welcome!',
                'goodbye.message' => 'Goodbye!'
            ]
        ];

        $response = $this->post(cp_route('utilities.string-translations'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('localized_strings', [
            'key' => 'welcome.message',
            'lang' => 'en',
            'value' => 'Welcome!'
        ]);
    }

    public function test_make_deletes_keys()
    {
        LocalizedString::create([
            'key' => 'old.key',
            'lang' => 'en',
            'value' => 'Old value'
        ]);

        $data = [
            'lang' => 'en',
            'strings' => [],
            'keys_to_delete' => 'old.key'
        ];

        $response = $this->post(cp_route('utilities.string-translations'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('localized_strings', [
            'key' => 'old.key'
        ]);
    }

    public function test_make_validates_input()
    {
        $data = [
            'lang' => '', // Invalid
            'strings' => [
                'key' => str_repeat('a', 2001) // Too long
            ]
        ];

        $response = $this->post(cp_route('utilities.string-translations'), $data);

        $response->assertSessionHasErrors(['lang', 'strings.key']);
    }
}
