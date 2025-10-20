<?php

namespace AgencyOrgo\StringTranslations\Tests\Models;

use AgencyOrgo\StringTranslations\Models\LocalizedString;
use AgencyOrgo\StringTranslations\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LocalizedStringTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_localized_string()
    {
        $string = LocalizedString::create([
            'key' => 'welcome.message',
            'lang' => 'en',
            'value' => 'Welcome to our site!'
        ]);

        $this->assertDatabaseHas('localized_strings', [
            'key' => 'welcome.message',
            'lang' => 'en',
            'value' => 'Welcome to our site!'
        ]);
    }

    public function test_unique_constraint_on_key_and_lang()
    {
        LocalizedString::create([
            'key' => 'welcome.message',
            'lang' => 'en',
            'value' => 'Welcome!'
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        
        LocalizedString::create([
            'key' => 'welcome.message',
            'lang' => 'en',
            'value' => 'Welcome again!'
        ]);
    }

    public function test_can_have_same_key_different_languages()
    {
        LocalizedString::create([
            'key' => 'welcome.message',
            'lang' => 'en',
            'value' => 'Welcome!'
        ]);

        LocalizedString::create([
            'key' => 'welcome.message',
            'lang' => 'es',
            'value' => '¡Bienvenido!'
        ]);

        $this->assertDatabaseCount('localized_strings', 2);
    }
}