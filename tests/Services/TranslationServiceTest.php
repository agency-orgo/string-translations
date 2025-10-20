<?php

namespace AgencyOrgo\StringTranslations\Tests\Services;

use AgencyOrgo\StringTranslations\Models\LocalizedString;
use AgencyOrgo\StringTranslations\Services\TranslationService;
use AgencyOrgo\StringTranslations\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

class TranslationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure lang directory exists
        if (!File::exists(base_path('lang'))) {
            File::makeDirectory(base_path('lang'));
        }
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (File::exists(base_path('lang/en.json'))) {
            File::delete(base_path('lang/en.json'));
        }
        
        parent::tearDown();
    }

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

    public function test_save_json_file()
    {
        $translations = [
            'welcome.message' => 'Welcome!',
            'goodbye.message' => 'Goodbye!'
        ];

        TranslationService::save('en', $translations);

        $this->assertTrue(File::exists(base_path('lang/en.json')));
        
        $content = json_decode(File::get(base_path('lang/en.json')), true);
        $this->assertEquals($translations, $content);
    }

    public function test_get_translation_from_json()
    {
        $translations = [
            'welcome.message' => 'Welcome!'
        ];

        TranslationService::save('en', $translations);
        
        $result = TranslationService::get('en', 'welcome.message');
        $this->assertEquals('Welcome!', $result);
    }

    public function test_set_translation()
    {
        TranslationService::set('en', 'new.key', 'New value');
        
        $this->assertTrue(File::exists(base_path('lang/en.json')));
        
        $content = json_decode(File::get(base_path('lang/en.json')), true);
        $this->assertEquals(['new.key' => 'New value'], $content);
    }
}