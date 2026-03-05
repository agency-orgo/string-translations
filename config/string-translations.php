<?php

return [
    'database' => [
        'connection' => env('STRING_TRANSLATIONS_DB_CONNECTION', 'default'),
        'table' => env('STRING_TRANSLATIONS_TABLE', 'localized_strings'),
        'settings_table' => env('STRING_TRANSLATIONS_SETTINGS_TABLE', 'string_translation_settings'),
    ],

    'untranslated_prefix' => env('STRING_TRANSLATIONS_UNTRANSLATED_PREFIX', 'untranslated_'),

    'api' => [
        'enabled' => env('STRING_TRANSLATIONS_API_ENABLED', false),
    ],
];