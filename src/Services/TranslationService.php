<?php

namespace AgencyOrgo\StringTranslations\Services;

use AgencyOrgo\StringTranslations\Contracts\TranslationRepository;
use AgencyOrgo\StringTranslations\Events\TranslationsDeleted;
use AgencyOrgo\StringTranslations\Events\TranslationsSaved;

class TranslationService
{
    private static function repo(): TranslationRepository
    {
        return app(TranslationRepository::class);
    }

    /**
     * Despite the name, this writes to whichever store storage.driver selects.
     */
    public static function saveToDatabase(string $language, array $translations, array $keysToDelete = []): void
    {
        $deletedKeys = [];
        $savedKeys = [];

        self::repo()->transaction(function () use ($language, $translations, $keysToDelete, &$deletedKeys, &$savedKeys) {
            if (!empty($keysToDelete)) {
                $keysToDelete = array_filter(
                    array_map('trim', $keysToDelete),
                    fn (string $key) => $key !== '' && strlen($key) <= 255
                );

                $deletedKeys = self::repo()->deleteKeys(array_values($keysToDelete));

                // Defensive: ensure translations do not include keys slated for deletion
                foreach ($keysToDelete as $deleteKey) {
                    unset($translations[$deleteKey]);
                }
            }

            $savedKeys = self::bulkUpsertTranslations($language, $translations);
        });

        // Fire events outside the transaction so listeners that touch the cache
        // (or other persistence) don't run inside the DB lock.
        if (!empty($deletedKeys)) {
            TranslationsDeleted::dispatch($deletedKeys);
        }
        if (!empty($savedKeys)) {
            TranslationsSaved::dispatch($language, $savedKeys);
        }
    }

    /**
     * Unchanged keys are skipped entirely, so a no-op save doesn't rewrite files
     * or queue an empty git commit.
     *
     * @return array<int, string>  Keys whose stored value changed.
     */
    private static function bulkUpsertTranslations(string $language, array $translations): array
    {
        $translations = array_filter($translations, fn ($key) => self::isValidKey($key), ARRAY_FILTER_USE_KEY);

        if (empty($translations)) {
            return [];
        }

        $existing = self::repo()->forLang($language);

        $changed = [];
        foreach ($translations as $key => $value) {
            if (! isset($existing[$key]) || $existing[$key]['value'] !== $value) {
                $changed[$key] = $value;
            }
        }

        if (! empty($changed)) {
            self::repo()->upsert($language, $changed, autoTranslated: false);
        }

        return array_keys($changed);
    }

    public static function bulkUpsertAutoTranslations(string $language, array $translations): void
    {
        $translations = array_filter($translations, fn ($key) => self::isValidKey($key), ARRAY_FILTER_USE_KEY);

        if (empty($translations)) {
            return;
        }

        self::repo()->upsert($language, $translations, autoTranslated: true);

        TranslationsSaved::dispatch($language, array_keys($translations));
    }

    public static function bulkUpsertPreserveFlag(string $language, array $translations): void
    {
        $translations = array_filter($translations, fn ($key) => self::isValidKey($key), ARRAY_FILTER_USE_KEY);

        if (empty($translations)) {
            return;
        }

        self::repo()->upsert($language, $translations, autoTranslated: null);

        TranslationsSaved::dispatch($language, array_keys($translations));
    }

    private static function isValidKey(string $key): bool
    {
        $key = trim($key);

        return $key !== '' && strlen($key) <= 255 && preg_match('/^[a-zA-Z0-9._ -]+$/', $key);
    }
}
