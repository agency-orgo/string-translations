<?php

use AgencyOrgo\StringTranslations\Events\TranslationsDeleted;
use AgencyOrgo\StringTranslations\Events\TranslationsSaved;
use AgencyOrgo\StringTranslations\Models\LocalizedString;
use AgencyOrgo\StringTranslations\Support\YamlTranslationStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

Artisan::command("strings:import {--force : Delete all existing translations before importing}", function () {
    $store = app(YamlTranslationStore::class);

    $translations = [];
    $auto = [];

    foreach ($store->locales() as $lang) {
        $translations[$lang] = $store->read($lang);
    }
    foreach ($store->meta() as $lang => $keys) {
        $auto[$lang] = $keys;
    }

    // Older versions exported to lang/*.json, keep reading those.
    $langPath = base_path('lang');
    if (File::isDirectory($langPath)) {
        foreach (File::glob($langPath.'/*.json') as $file) {
            $lang = pathinfo($file, PATHINFO_FILENAME);
            $translations[$lang] = array_merge(
                $translations[$lang] ?? [],
                json_decode(File::get($file), true) ?: []
            );
        }

        $metaFile = $langPath.'/.auto_translated.json';
        if (File::exists($metaFile)) {
            foreach (json_decode(File::get($metaFile), true) ?? [] as $lang => $keys) {
                $auto[$lang] = array_merge($auto[$lang] ?? [], $keys);
            }
        }
    }

    $rows = [];
    foreach ($translations as $lang => $pairs) {
        foreach ($pairs as $k => $v) {
            $rows[] = [
                'key' => $k,
                'lang' => $lang,
                'value' => $v,
                'auto_translated' => !empty($auto[$lang][$k]),
            ];
        }
    }
    if ($this->option('force')) {
        $deletedKeys = LocalizedString::select('key')->distinct()->pluck('key')->all();
        LocalizedString::truncate();
        $this->warn('Deleted all existing translations.');

        if (!empty($deletedKeys)) {
            TranslationsDeleted::dispatch($deletedKeys);
        }
    }

    $inserted = LocalizedString::insertOrIgnore($rows);

    if ($inserted > 0) {
        foreach ($translations as $lang => $pairs) {
            $keys = array_keys($pairs);
            if (!empty($keys)) {
                TranslationsSaved::dispatch($lang, $keys);
            }
        }
    }

    $this->info('Imported ' . count($rows) . ' translations into the database.');
});

Artisan::command("strings:export", function () {
    $strings = LocalizedString::all();

    if ($strings->isEmpty()) {
        $this->warn('No translations found in the database.');
        return;
    }

    $store = app(YamlTranslationStore::class);
    $meta = [];

    foreach ($strings->groupBy('lang') as $locale => $rows) {
        $values = $rows->sortBy('key')->pluck('value', 'key')->toArray();
        $store->write($locale, $values);

        $autoKeys = $rows->filter(fn ($t) => (bool) $t->auto_translated)->pluck('key')->all();
        if (!empty($autoKeys)) {
            $meta[$locale] = array_fill_keys($autoKeys, true);
        }

        $this->info("Exported " . count($values) . " strings to {$locale}.yaml");
    }

    $store->writeMeta($meta);

    $this->info("Exported to {$store->path()}");
});
