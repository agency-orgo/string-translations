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


    // Load auto_translated metadata if it exists
    $metaFile = $langPath . '/.auto_translated.json';
    $autoTranslatedMeta = [];
    if (File::exists($metaFile)) {
        $autoTranslatedMeta = json_decode(File::get($metaFile), true) ?? [];
    }

    $rows = [];
    foreach ($translations as $lang => $pairs) {
        foreach ($pairs as $k => $v) {
            $rows[] = [
                'key' => $k,
                'lang' => $lang,
                'value' => $v,
                'auto_translated' => $autoTranslatedMeta[$lang][$k] ?? false,
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

    $autoTranslatedMeta = [];

    foreach ($grouped as $locale => $translations) {
        $sorted = $translations->sortBy('key');

        $data = $sorted->pluck('value', 'key')->toArray();

        $autoKeys = $sorted->filter(fn ($t) => (bool) $t->auto_translated)->pluck('key')->all();
        if (!empty($autoKeys)) {
            $autoTranslatedMeta[$locale] = array_fill_keys($autoKeys, true);
        }

        $file = $langPath . "/{$locale}.json";
        File::put($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Exported " . count($data) . " strings to {$locale}.json");
    }

    $metaFile = $langPath . '/.auto_translated.json';
    if (!empty($autoTranslatedMeta)) {
        File::put($metaFile, json_encode($autoTranslatedMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Exported auto_translated metadata to .auto_translated.json");
    } elseif (File::exists($metaFile)) {
        File::delete($metaFile);
    }
});
