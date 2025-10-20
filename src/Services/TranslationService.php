<?php

namespace AgencyOrgo\StringTranslations\Services;

use AgencyOrgo\StringTranslations\Models\LocalizedString;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class TranslationService
{
    public static function all($locale)
    {
        $path = pathinfo(self::getPath($locale), PATHINFO_DIRNAME);
        if (!File::exists($path)) {
            File::makeDirectory($path);

        }
        if (!File::exists(self::getPath($locale))) {
            self::save($locale, []);
        }

        return json_decode(
            File::get(self::getPath($locale)),
            true,
            2,
            JSON_THROW_ON_ERROR
        );
    }

    public static function save($locale, $strings): void
    {
        $path = pathinfo(self::getPath($locale), PATHINFO_DIRNAME);
        if (!File::exists($path)) {
            File::makeDirectory($path);
        }

        File::put(
            self::getPath($locale),
            json_encode($strings, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        );
    }

    public static function get($locale, $key)
    {
        $strings = self::all($locale);
        if (isset($strings[$key])) {
            return $strings[$key];
        }

        $strings = self::all(config('app.fallback_locale'));

        return $strings[$key];
    }

    public static function set($locale, $key, $value)
    {
        $strings = self::all($locale);

        if (!isset($strings[$key]) && $key && $value) {
            $strings[$key] = $value;
            self::save($locale, $strings);
        }
    }

    public static function add($key, $value)
    {
        foreach (config('statamic.sites.sites') as $site => $siteInfo) {
            self::set($site, $key, $value);
        }
    }

    private static function getPath($locale): string
    {
        return base_path() . "/lang/{$locale}.json";
    }

    /**
     * Save translations to database with bulk operations for performance.
     * 
     * @param string $language
     * @param array $translations
     * @param array $keysToDelete
     * @return void
     */
    public static function saveToDatabase(string $language, array $translations, array $keysToDelete = []): void
    {
        DB::transaction(function () use ($language, $translations, $keysToDelete) {
            // 1. Handle cross-locale deletions first
            if (!empty($keysToDelete)) {
                self::deleteKeysFromAllLocales($keysToDelete);
            }

            // 1b. Defensive: ensure translations do not include keys slated for deletion
            if (!empty($keysToDelete) && !empty($translations)) {
                $validDeletionKeys = self::validateKeys($keysToDelete);
                if (!empty($validDeletionKeys)) {
                    foreach ($validDeletionKeys as $deleteKey) {
                        unset($translations[$deleteKey]);
                    }
                }
            }

            // 2. Clean up obsolete translations for current language
            self::cleanupObsoleteTranslations($language, $translations, $keysToDelete);

            // 3. Bulk upsert translations for current language
            self::bulkUpsertTranslations($language, $translations);
        });
    }

    /**
     * Delete specific keys from all locales.
     */
    private static function deleteKeysFromAllLocales(array $keys): void
    {
        // Validate and sanitize keys
        $validKeys = self::validateKeys($keys);

        if (empty($validKeys)) {
            return;
        }

        // Perform deletion across all locales
        LocalizedString::whereIn('key', $validKeys)->delete();
    }

    /**
     * Clean up translations that are no longer present in the submitted data.
     */
    private static function cleanupObsoleteTranslations(string $language, array $translations, array $excludedKeys = []): void
    {
        $submittedKeys = array_keys($translations);
        $allKeysToKeep = array_merge($submittedKeys, $excludedKeys);

        // Optimize for large datasets
        if (count($allKeysToKeep) > 1000) {
            // For large datasets, fetch existing keys first to avoid query length limits
            $existingKeysToDelete = LocalizedString::where('lang', $language)
                ->select('key')
                ->get()
                ->pluck('key')
                ->diff($allKeysToKeep);

            if ($existingKeysToDelete->isNotEmpty()) {
                LocalizedString::where('lang', $language)
                    ->whereIn('key', $existingKeysToDelete->toArray())
                    ->delete();
            }
        } else {
            // Standard approach for smaller datasets
            LocalizedString::where('lang', $language)
                ->whereNotIn('key', $allKeysToKeep)
                ->delete();
        }
    }

    /**
     * Perform bulk upsert of translations for optimal performance.
     */
    private static function bulkUpsertTranslations(string $language, array $translations): void
    {
        if (empty($translations)) {
            return;
        }

        // Prepare bulk upsert data
        $upsertData = [];
        $now = now();

        foreach ($translations as $key => $value) {
            $upsertData[] = [
                'key' => $key,
                'lang' => $language,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        try {
            // Use Laravel's bulk upsert for optimal performance
            LocalizedString::upsert(
                $upsertData,
                ['key', 'lang'], // Unique columns
                ['value', 'updated_at'] // Columns to update if exists
            );
        } catch (\Exception $e) {
            // Fallback to individual operations
            foreach ($translations as $key => $value) {
                LocalizedString::updateOrCreate(
                    ['key' => $key, 'lang' => $language],
                    ['value' => $value, 'updated_at' => $now]
                );
            }
        }
    }

    /**
     * Validate and sanitize translation keys.
     */
    private static function validateKeys(array $keys): array
    {
        return array_filter(
            array_map('trim', $keys),
            function ($key) {
                // Allow alphanumeric, dots, underscores, spaces, and hyphens
                return !empty($key)
                    && is_string($key)
                    && preg_match('/^[a-zA-Z0-9._$\\s-]+$/', $key)
                    && strlen($key) <= 255;
            }
        );
    }
}
