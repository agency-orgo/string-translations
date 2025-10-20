<?php

return [
    'database' => [
        'connection' => env('STRING_TRANSLATIONS_DB_CONNECTION', 'default'),
        'table' => env('STRING_TRANSLATIONS_TABLE', 'localized_strings'),
    ],
];