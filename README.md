# String Translations

A Statamic addon for managing string translations with database storage and fallback support.

## Features

- Database-driven string translations
- Multi-language support with fallback hierarchy
- Bulk operations for performance
- Search and filter functionality
- Control Panel integration
- REST API and GraphQL support

## Installation

You can install this addon via Composer:

```bash
composer require agency-orgo/string-translations
```

## Usage

After installation, you'll find "String Translations" in your Statamic Control Panel under Utilities.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=string-translations-config
```

```php
return [
    'database' => [
        'connection' => env('STRING_TRANSLATIONS_DB_CONNECTION', 'default'),
        'table' => env('STRING_TRANSLATIONS_TABLE', 'localized_strings'),
    ],
    'api' => [
        'enabled' => env('STRING_TRANSLATIONS_API_ENABLED', false),
    ],
];
```

## REST API

Enable with `STRING_TRANSLATIONS_API_ENABLED=true` in your `.env`.

**Fetch translations:**

```bash
curl "https://your-site.com/!/string-translations/strings?lang=en"
```

**Create keys:**

```bash
curl -X POST "https://your-site.com/!/string-translations/strings" \
  -H "Content-Type: application/json" \
  -d '{"keys": ["nav.home", "nav.about"]}'
```

## GraphQL

Automatically available when Statamic's GraphQL is enabled (`STATAMIC_GRAPHQL_ENABLED=true`). No additional configuration needed.

### Fetch translations

```graphql
{
  string_translations(lang: "en") {
    lang
    strings
  }
}
```

Response:

```json
{
  "data": {
    "string_translations": {
      "lang": "en",
      "strings": {
        "nav.home": "Home",
        "welcome.message": "Welcome!"
      }
    }
  }
}
```

### Create translation keys

Creates keys across all configured sites with an `untranslated_` prefix.

```graphql
mutation {
  createStringTranslations(keys: ["nav.contact", "footer.copyright"]) {
    created
  }
}
```

Response:

```json
{
  "data": {
    "createStringTranslations": {
      "created": 12
    }
  }
}
```

The `created` count reflects total rows inserted (keys * sites). Duplicate keys are ignored.

## Events

The addon dispatches Statamic-style content events for every write so other parts of the system can react. Both extend `Statamic\Events\Event`.

| Event | When |
| --- | --- |
| `AgencyOrgo\StringTranslations\Events\TranslationsSaved` | Any insert or value change. Carries `$lang` (the locale, or `null` for cross-locale operations like `createStringTranslations`) and `$keys` (the affected keys). |
| `AgencyOrgo\StringTranslations\Events\TranslationsDeleted` | Cross-locale key removal. Carries `$keys`. |

### GraphQL response cache

Statamic's GraphQL response cache (`config/statamic/graphql.php` → `cache.expiry`, default 60min) is automatically invalidated whenever a translation event fires — the addon registers a listener in its service provider that calls `Statamic\Contracts\GraphQL\ResponseCache::handleInvalidationEvent()`. Frontends consuming `string_translations(lang: …)` over GraphQL will see saves on the next request without any manual `php artisan cache:clear`. The listener is skipped when GraphQL is disabled or when `statamic.graphql.cache` is `false`.

### Listening to events

```php
use AgencyOrgo\StringTranslations\Events\TranslationsSaved;
use Illuminate\Support\Facades\Event;

Event::listen(TranslationsSaved::class, function (TranslationsSaved $event) {
    // $event->lang, $event->keys
});
```

## Requirements

- Statamic 6.0+
- PHP 8.3+
