<?php

namespace AgencyOrgo\StringTranslations\Tests\Events;

use AgencyOrgo\StringTranslations\Controllers\ApiController;
use AgencyOrgo\StringTranslations\Events\TranslationsDeleted;
use AgencyOrgo\StringTranslations\Events\TranslationsSaved;
use AgencyOrgo\StringTranslations\GraphQL\Mutations\CreateStringTranslationsMutation;
use AgencyOrgo\StringTranslations\Models\LocalizedString;
use AgencyOrgo\StringTranslations\Services\TranslationService;
use AgencyOrgo\StringTranslations\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

class EventDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_to_database_dispatches_translations_saved()
    {
        Event::fake([TranslationsSaved::class]);

        TranslationService::saveToDatabase('en', ['hello' => 'Hello']);

        Event::assertDispatched(TranslationsSaved::class, function ($event) {
            return $event->lang === 'en' && $event->keys === ['hello'];
        });
    }

    public function test_save_to_database_does_not_dispatch_when_values_are_unchanged()
    {
        LocalizedString::create(['key' => 'hello', 'lang' => 'en', 'value' => 'Hello']);

        Event::fake([TranslationsSaved::class]);

        TranslationService::saveToDatabase('en', ['hello' => 'Hello']);

        Event::assertNotDispatched(TranslationsSaved::class);
    }

    public function test_save_to_database_dispatches_deleted_event_for_deletions()
    {
        LocalizedString::create(['key' => 'doomed', 'lang' => 'en', 'value' => 'x']);
        LocalizedString::create(['key' => 'doomed', 'lang' => 'es', 'value' => 'x']);

        Event::fake([TranslationsDeleted::class]);

        TranslationService::saveToDatabase('en', [], ['doomed']);

        Event::assertDispatched(TranslationsDeleted::class, function ($event) {
            return $event->keys === ['doomed'];
        });
    }

    public function test_bulk_upsert_auto_translations_dispatches_saved_event()
    {
        Event::fake([TranslationsSaved::class]);

        TranslationService::bulkUpsertAutoTranslations('en', ['hello' => 'Hello']);

        Event::assertDispatched(TranslationsSaved::class, function ($event) {
            return $event->lang === 'en' && $event->keys === ['hello'];
        });
    }

    public function test_api_controller_store_dispatches_saved_event()
    {
        Event::fake([TranslationsSaved::class]);

        $controller = new ApiController();
        $request = Request::create('/strings', 'POST', ['keys' => ['nav.home']]);
        $request->setLaravelSession(app('session.store'));
        $controller->store($request);

        Event::assertDispatched(TranslationsSaved::class, function ($event) {
            return $event->lang === null && $event->keys === ['nav.home'];
        });
    }

    public function test_graphql_mutation_dispatches_saved_event()
    {
        Event::fake([TranslationsSaved::class]);

        $mutation = new CreateStringTranslationsMutation();
        $mutation->resolve(null, ['keys' => ['footer.copyright']]);

        Event::assertDispatched(TranslationsSaved::class, function ($event) {
            return $event->lang === null && $event->keys === ['footer.copyright'];
        });
    }
}
