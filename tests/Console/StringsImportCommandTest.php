<?php

namespace AgencyOrgo\StringTranslations\Tests\Console;

use AgencyOrgo\StringTranslations\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

class StringsImportCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!File::exists(base_path('lang'))) {
            File::makeDirectory(base_path('lang'));
        }

        // Ensure the directory is empty, avoiding vendor stub files
        File::cleanDirectory(base_path('lang'));
    }

    protected function tearDown(): void
    {
        if (File::isDirectory(base_path('lang'))) {
            File::cleanDirectory(base_path('lang'));
        }

        parent::tearDown();
    }

    public function test_strings_import_command()
    {
        // Create test JSON files
        File::put(base_path('lang/en.json'), json_encode([
            'welcome.message' => 'Welcome!',
            'goodbye.message' => 'Goodbye!'
        ]));

        File::put(base_path('lang/es.json'), json_encode([
            'welcome.message' => '¡Bienvenido!',
            'goodbye.message' => '¡Adiós!'
        ]));

        $this->artisan('strings:import')
            ->assertExitCode(0);

        $this->assertDatabaseHas('localized_strings', [
            'key' => 'welcome.message',
            'lang' => 'en',
            'value' => 'Welcome!'
        ]);

        $this->assertDatabaseHas('localized_strings', [
            'key' => 'welcome.message',
            'lang' => 'es',
            'value' => '¡Bienvenido!'
        ]);

        $this->assertDatabaseCount('localized_strings', 4);
    }

    public function test_strings_import_with_empty_directory()
    {
        $this->artisan('strings:import')
            ->assertExitCode(0);

        $this->assertDatabaseCount('localized_strings', 0);
    }
}