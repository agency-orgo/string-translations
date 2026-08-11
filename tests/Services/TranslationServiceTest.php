<?php

namespace AgencyOrgo\StringTranslations\Tests\Services;

use AgencyOrgo\StringTranslations\Models\LocalizedString;
use AgencyOrgo\StringTranslations\Services\TranslationService;
use AgencyOrgo\StringTranslations\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TranslationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_to_database_with_valid_data()
    {
        $translations = [
            'welcome.message' => 'Welcome!',
            'goodbye.message' => 'Goodbye!'
        ];

        TranslationService::saveToDatabase('en', $translations);

        $this->assertDatabaseHas('localized_strings', [
            'key' => 'welcome.message',
            'lang' => 'en',
            'value' => 'Welcome!'
        ]);

        $this->assertDatabaseHas('localized_strings', [
            'key' => 'goodbye.message',
            'lang' => 'en',
            'value' => 'Goodbye!'
        ]);
    }

    public function test_save_to_database_with_deletions()
    {
        // Create initial translations
        LocalizedString::create([
            'key' => 'old.key',
            'lang' => 'en',
            'value' => 'Old value'
        ]);

        LocalizedString::create([
            'key' => 'old.key',
            'lang' => 'es',
            'value' => 'Valor viejo'
        ]);

        $translations = [
            'new.key' => 'New value'
        ];

        TranslationService::saveToDatabase('en', $translations, ['old.key']);

        // Old key should be deleted from all locales
        $this->assertDatabaseMissing('localized_strings', [
            'key' => 'old.key'
        ]);

        // New key should exist
        $this->assertDatabaseHas('localized_strings', [
            'key' => 'new.key',
            'lang' => 'en',
            'value' => 'New value'
        ]);
    }
}
