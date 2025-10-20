<?php

namespace AgencyOrgo\StringTranslations\Tests\Integration;

use AgencyOrgo\StringTranslations\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StringTranslationsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_workflow()
    {
        // 1. Create translations via controller
        $data = [
            'lang' => 'en',
            'strings' => [
                'welcome.message' => 'Welcome!',
                'goodbye.message' => 'Goodbye!'
            ]
        ];

        $this->post(cp_route('utilities.string-translations'), $data);

        // 2. Verify they exist in database
        $this->assertDatabaseCount('localized_strings', 2);

        // 3. Update translations
        $updateData = [
            'lang' => 'en',
            'strings' => [
                'welcome.message' => 'Welcome Updated!',
                'goodbye.message' => 'Goodbye!',
                'new.message' => 'New message!'
            ]
        ];

        $this->post(cp_route('utilities.string-translations'), $updateData);

        // 4. Verify updates
        $this->assertDatabaseHas('localized_strings', [
            'key' => 'welcome.message',
            'lang' => 'en',
            'value' => 'Welcome Updated!'
        ]);

        $this->assertDatabaseHas('localized_strings', [
            'key' => 'new.message',
            'lang' => 'en',
            'value' => 'New message!'
        ]);

        // 5. Delete a key
        $deleteData = [
            'lang' => 'en',
            'strings' => [
                'welcome.message' => 'Welcome Updated!',
                'new.message' => 'New message!'
            ],
            'keys_to_delete' => 'goodbye.message'
        ];

        $this->post(cp_route('utilities.string-translations'), $deleteData);

        // 6. Verify deletion
        $this->assertDatabaseMissing('localized_strings', [
            'key' => 'goodbye.message'
        ]);

        $this->assertDatabaseCount('localized_strings', 2);
    }
}