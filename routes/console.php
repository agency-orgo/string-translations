<?php

use AgencyOrgo\StringTranslations\Models\LocalizedString;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

Artisan::command("strings:import {--force : Delete all existing translations before importing}", function () {
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
    if ($this->option('force')) {
        LocalizedString::truncate();
        $this->warn('Deleted all existing translations.');
    }

    LocalizedString::insertOrIgnore($rows);

    $this->info('Imported ' . count($rows) . ' translations.');
});

Artisan::command("strings:export", function () {
    $strings = LocalizedString::all();

    if ($strings->isEmpty()) {
        $this->warn('No translations found in the database.');
        return;
    }

    $grouped = $strings->groupBy('lang');
    $langPath = base_path('lang');

    if (!File::isDirectory($langPath)) {
        File::makeDirectory($langPath, 0755, true);
    }

    foreach ($grouped as $locale => $translations) {
        $data = $translations->pluck('value', 'key')->sortKeys()->toArray();
        $file = $langPath . "/{$locale}.json";
        File::put($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Exported " . count($data) . " strings to {$locale}.json");
    }
});
