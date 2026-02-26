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
  stringTranslations(lang: "en") {
    lang
    strings
  }
}
```

Response:

```json
{
  "data": {
    "stringTranslations": {
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

## Requirements

- Statamic 6.0+
- PHP 8.3+
