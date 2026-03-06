<?php

namespace AgencyOrgo\StringTranslations\Services;

use AgencyOrgo\StringTranslations\Models\LocalizedString;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Site;

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
        foreach (Site::all() as $handle => $site) {
            self::set($handle, $key, $value);
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
                $trimmedKeysToDelete = array_map('trim', $keysToDelete);
                foreach ($trimmedKeysToDelete as $deleteKey) {
                    unset($translations[$deleteKey]);
                }
            }

            // 2. Bulk upsert translations for current language
            self::bulkUpsertTranslations($language, $translations);
        });
    }

    /**
     * Delete specific keys from all locales. Uses permissive filtering
     * so keys that don't pass strict validation can still be removed.
     */
    private static function deleteKeysFromAllLocales(array $keys): void
    {
        $keys = array_filter(array_map('trim', $keys), fn (string $key) => $key !== '' && strlen($key) <= 255);

        if (empty($keys)) {
            return;
        }

        LocalizedString::whereIn('key', $keys)->delete();
    }

    /**
     * Perform bulk upsert of translations for optimal performance.
     * Only clears auto_translated for keys whose value actually changed.
     */
    private static function bulkUpsertTranslations(string $language, array $translations): void
    {
        $translations = array_filter($translations, fn ($key) => self::isValidKey($key), ARRAY_FILTER_USE_KEY);

        if (empty($translations)) {
            return;
        }

        // Fetch current values to detect which keys actually changed
        $existing = LocalizedString::where('lang', $language)
            ->whereIn('key', array_keys($translations))
            ->pluck('value', 'key')
            ->all();

        $changed = [];
        $unchanged = [];

        foreach ($translations as $key => $value) {
            if (! isset($existing[$key]) || $existing[$key] !== $value) {
                $changed[$key] = $value;
            } else {
                $unchanged[$key] = $value;
            }
        }

        // Changed keys: force auto_translated = false
        if (! empty($changed)) {
            self::bulkUpsert($language, $changed, autoTranslated: false);
        }

        // Unchanged keys: upsert value only, preserve auto_translated
        if (! empty($unchanged)) {
            self::bulkUpsertPreserveFlag($language, $unchanged);
        }
    }

    /**
     * Bulk upsert translations marked as auto-translated (from DeepL).
     */
    public static function bulkUpsertAutoTranslations(string $language, array $translations): void
    {
        self::bulkUpsert($language, $translations, autoTranslated: true);
    }

    /**
     * Upsert translations without touching the auto_translated flag.
     */
    public static function bulkUpsertPreserveFlag(string $language, array $translations): void
    {
        $translations = array_filter($translations, fn ($key) => self::isValidKey($key), ARRAY_FILTER_USE_KEY);

        if (empty($translations)) {
            return;
        }

        $now = now();
        $upsertData = [];

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
            LocalizedString::upsert(
                $upsertData,
                ['key', 'lang'],
                ['value', 'updated_at']
            );
        } catch (\Exception $e) {
            report($e);

            foreach ($translations as $key => $value) {
                LocalizedString::updateOrCreate(
                    ['key' => $key, 'lang' => $language],
                    ['value' => $value, 'updated_at' => $now]
                );
            }
        }
    }

    private static function bulkUpsert(string $language, array $translations, bool $autoTranslated): void
    {
        $translations = array_filter($translations, fn ($key) => self::isValidKey($key), ARRAY_FILTER_USE_KEY);

        if (empty($translations)) {
            return;
        }

        $now = now();
        $upsertData = [];

        foreach ($translations as $key => $value) {
            $upsertData[] = [
                'key' => $key,
                'lang' => $language,
                'value' => $value,
                'auto_translated' => $autoTranslated,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        try {
            LocalizedString::upsert(
                $upsertData,
                ['key', 'lang'],
                ['value', 'auto_translated', 'updated_at']
            );
        } catch (\Exception $e) {
            report($e);

            foreach ($translations as $key => $value) {
                LocalizedString::updateOrCreate(
                    ['key' => $key, 'lang' => $language],
                    ['value' => $value, 'auto_translated' => $autoTranslated, 'updated_at' => $now]
                );
            }
        }
    }

    private static function isValidKey(string $key): bool
    {
        $key = trim($key);

        return $key !== '' && strlen($key) <= 255 && preg_match('/^[a-zA-Z0-9._ -]+$/', $key);
    }
}
