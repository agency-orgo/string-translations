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
