<?php

use AgencyOrgo\StringTranslations\Models\LocalizedString;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

Artisan::command("strings:import", function () {
    $langPath = base_path('lang');
    $translations = [];

    if (File::isDirectory($langPath)) {
        // Get all JSON files in the lang directory
        $files = File::glob($langPath.'/*.json');

        foreach ($files as $file) {
            $locale = pathinfo($file, PATHINFO_FILENAME); // e.g., 'en' from 'en.json'
            $content = File::get($file);
            $translations[$locale] = json_decode($content, true);
        }
    }


    $rows = [];
    foreach ($translations as $lang => $pairs) {
        foreach ($pairs as $k => $v) {
            $rows[] = [
                "key" => $k,
                "lang" => $lang,
                "value" => $v,
            ];
        }
    }
    LocalizedString::insertOrIgnore($rows);
});

Artisan::command("strings:export", function () {
    $translations = LocalizedString::orderBy('key')->get()->groupBy('lang');

    $langPath = base_path('lang');
    if (!File::isDirectory($langPath)) {
        File::makeDirectory($langPath, 0755, true);
    }

    foreach ($translations as $locale => $entries) {
        $data = [];
        foreach ($entries as $entry) {
            $data[$entry->key] = $entry->value;
        }
        ksort($data);

        File::put($langPath . "/{$locale}.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    $this->info('Export complete.');
});
