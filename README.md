# String Translations

A Statamic addon for managing string translations with database or flat-file storage and fallback support.

## Features

- Database or flat YAML file storage
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
    'storage' => [
        'driver' => env('STRING_TRANSLATIONS_DRIVER', 'database'), // 'database' | 'yaml'
        'path' => env('STRING_TRANSLATIONS_PATH', resource_path('translations')),
    ],
    'database' => [
        'connection' => env('STRING_TRANSLATIONS_DB_CONNECTION', 'default'),
        'table' => env('STRING_TRANSLATIONS_TABLE', 'localized_strings'),
    ],
    'api' => [
        'enabled' => env('STRING_TRANSLATIONS_API_ENABLED', false),
    ],
];
```

## Storage drivers

`storage.driver` picks the one store the addon reads and writes. There is no mirroring between them.

### `database` (default)

Translations live in the `localized_strings` table. Run `php artisan migrate` after installing. Nothing changes if you leave `storage.driver` alone.

### `yaml`

Set `STRING_TRANSLATIONS_DRIVER=yaml`. Translations become flat YAML files, one per locale, under `storage.path` (default `resources/translations`):

```
resources/translations/
  en.yaml        # key: value (sorted)
  et.yaml
  .meta.yaml     # which values came from DeepL
```

No database or migration is needed. Commit `resources/translations/` and the files travel with your deploys and show up in pull requests like any other content.

The DeepL API key is not written into that directory. Under the yaml driver it goes, encrypted, into `storage/string-translations/settings.yaml`, which Laravel's default `storage/` gitignore already covers.

### Switching drivers

Two commands move existing data between the stores. They are one-time migrations, not a sync:

```bash
php artisan strings:export   # database -> yaml files at storage.path
php artisan strings:import   # yaml files (and legacy lang/*.json) -> database
```

`strings:import --force` truncates the table before importing.

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
