<?php

namespace AgencyOrgo\StringTranslations\Controllers;

use AgencyOrgo\StringTranslations\Models\LocalizedString;
use AgencyOrgo\StringTranslations\Services\DeepLService;
use AgencyOrgo\StringTranslations\Services\SettingsService;
use AgencyOrgo\StringTranslations\Services\TranslationService;
use Composer\InstalledVersions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Statamic\Facades\Site;

class TranslationController
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    protected function getPath($locale): string
    {
        return base_path() . "/lang/{$locale}.json";
    }

    /**
     * Return props for the Inertia page component.
     */
    public function index(Request $request): array
    {
        $tableName = config('string-translations.database.table', 'localized_strings');
        $settingsTable = config('string-translations.database.settings_table', 'string_translation_settings');

        if (!Schema::hasTable($tableName) || !Schema::hasTable($settingsTable)) {
            return [
                'translations' => [],
                'activeLang' => $request->get('lang', 'en'),
                'sites' => $this->getSites(),
                'saveUrl' => cp_route('utilities.string-translations'),
                'settingsUrl' => cp_route('utilities.string-translations.settings.save'),
                'translateUrl' => cp_route('utilities.string-translations.translate-all'),
                'copyUrl' => cp_route('utilities.string-translations.copy-values'),
                'hasDeeplKey' => false,
                'missingTable' => true,
                'version' => self::getVersion(),
            ];
        }

        $validSites = Site::all()->keys()->all();
        $site = $request->get('lang', 'en');
        if (!in_array($site, $validSites, true)) {
            $site = $validSites[0] ?? 'en';
        }
        $this->propagateKeysIfEmpty($site);
        $fallbackSites = $this->getFallbackSites($site);
        $data = $this->getTranslationsWithFallback($site, $fallbackSites);

        return [
            'translations' => $data->map(fn ($entry) => [
                'key' => $entry->key,
                'value' => $entry->value,
                'untranslated' => str_starts_with($entry->value, config('string-translations.untranslated_prefix')),
                'auto_translated' => (bool) $entry->auto_translated,
            ])->values()->all(),
            'activeLang' => $site,
            'sites' => $this->getSites(),
            'saveUrl' => cp_route('utilities.string-translations'),
            'settingsUrl' => cp_route('utilities.string-translations.settings.save'),
            'translateUrl' => cp_route('utilities.string-translations.translate-all'),
            'copyUrl' => cp_route('utilities.string-translations.copy-values'),
            'hasDeeplKey' => $this->settings->has(SettingsService::DEEPL_API_KEY),
            'missingTable' => false,
            'version' => self::getVersion(),
        ];
    }

    public function make(Request $request)
    {
        // Validate request data
        $validated = $request->validate([
            'lang' => 'required|string|max:10',
            'keys_to_delete' => 'nullable|string|max:10000',
            'strings' => 'nullable|array',
            'strings.*' => 'string|max:2000',
        ]);

        try {
            // Parse keys to delete
            $keysToDelete = [];
            if (!empty($validated['keys_to_delete'])) {
                $keysToDelete = array_filter(
                    array_map('trim', explode(',', $validated['keys_to_delete']))
                );

                // Validate deletion limits
                if (count($keysToDelete) > 100) {
                    throw new \InvalidArgumentException('Too many keys selected for deletion. Maximum 100 keys allowed.');
                }
            }

            // Filter out any keys marked for deletion from submitted translations
            if (!empty($validated['strings'])) {
                foreach ($keysToDelete as $deleteKey) {
                    unset($validated['strings'][$deleteKey]);
                }
            }

            // Delegate to service layer
            TranslationService::saveToDatabase(
                $validated['lang'],
                $validated['strings'] ?? [],
                $keysToDelete
            );

            // Prepare success message
            $message = __('Saved.');
            if (!empty($keysToDelete)) {
                $deletedCount = count($keysToDelete);
                $message = __('Saved and deleted :count key(s) from all locales.', ['count' => $deletedCount]);
            }

            return back()->withSuccess($message);

        } catch (\InvalidArgumentException $e) {
            return back()->withError($e->getMessage());
        } catch (\Exception $e) {
            return back()->withError(__('An error occurred while saving. Please try again.'));
        }
    }

    public function getSettings(): JsonResponse
    {
        return response()->json([
            'has_deepl_key' => $this->settings->has(SettingsService::DEEPL_API_KEY),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deepl_api_key' => 'nullable|string|max:500',
        ]);

        $this->settings->set(
            SettingsService::DEEPL_API_KEY,
            $validated['deepl_api_key'] ?: null
        );

        return response()->json([
            'has_deepl_key' => $this->settings->has(SettingsService::DEEPL_API_KEY),
        ]);
    }

    public function translateAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_lang' => 'required|string|max:10',
            'to_lang' => 'required|string|max:10',
        ]);

        $apiKey = $this->settings->get(SettingsService::DEEPL_API_KEY);

        if (! $apiKey) {
            return response()->json(['error' => 'DeepL API key is not configured.'], 422);
        }

        $fromLang = $validated['from_lang'];
        $toLang = $validated['to_lang'];

        $fromLocale = $this->getLocaleForSite($fromLang);
        $toLocale = $this->getLocaleForSite($toLang);

        if (! $fromLocale || ! $toLocale) {
            return response()->json(['error' => 'Invalid site handle.'], 422);
        }

        try {
            return Cache::lock('string-translations:translate-all', 30)->block(0, function () use ($apiKey, $fromLang, $toLang, $fromLocale, $toLocale) {
                $untranslatedKeys = LocalizedString::where('lang', $toLang)
                    ->where('value', 'like', config('string-translations.untranslated_prefix') . '%')
                    ->pluck('key')
                    ->all();

                if (empty($untranslatedKeys)) {
                    return response()->json(['translated' => 0, 'message' => 'No untranslated keys found.']);
                }

                $sourceValues = LocalizedString::where('lang', $fromLang)
                    ->whereIn('key', $untranslatedKeys)
                    ->pluck('value', 'key')
                    ->all();

                $toTranslate = [];
                foreach ($untranslatedKeys as $key) {
                    if (isset($sourceValues[$key]) && ! str_starts_with($sourceValues[$key], config('string-translations.untranslated_prefix'))) {
                        $toTranslate[$key] = $sourceValues[$key];
                    }
                }

                if (empty($toTranslate)) {
                    return response()->json(['translated' => 0, 'message' => 'No translatable source values found.']);
                }

                try {
                    $deepl = new DeepLService($apiKey);
                    $keys = array_keys($toTranslate);
                    $texts = array_values($toTranslate);

                    $translated = $deepl->translate($fromLocale, $toLocale, $texts);

                    $translations = [];
                    foreach ($keys as $i => $key) {
                        $translations[$key] = $translated[$i];
                    }

                    TranslationService::bulkUpsertAutoTranslations($toLang, $translations);

                    return response()->json([
                        'translated' => count($translations),
                        'message' => count($translations) . ' key(s) translated successfully.',
                    ]);
                } catch (\Exception $e) {
                    report($e);

                    return response()->json(['error' => 'Translation failed. Please try again.'], 500);
                }
            });
        } catch (LockTimeoutException) {
            return response()->json(['error' => 'A translation is already in progress. Please wait.'], 429);
        }
    }

    public function copyValues(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_lang' => 'required|string|max:10',
            'to_lang' => 'required|string|max:10',
            'overwrite' => 'boolean',
        ]);

        $fromLang = $validated['from_lang'];
        $toLang = $validated['to_lang'];
        $overwrite = $validated['overwrite'] ?? false;
        $prefix = config('string-translations.untranslated_prefix');

        try {
            return Cache::lock('string-translations:copy-values', 30)->block(0, function () use ($fromLang, $toLang, $overwrite, $prefix) {
                // Get all source values, excluding untranslated ones
                $sourceValues = LocalizedString::where('lang', $fromLang)
                    ->where('value', 'not like', $prefix . '%')
                    ->pluck('value', 'key')
                    ->all();

                if (empty($sourceValues)) {
                    return response()->json(['copied' => 0, 'message' => 'No source values to copy.']);
                }

                if (! $overwrite) {
                    // Only copy to keys that are untranslated in destination
                    $untranslatedKeys = LocalizedString::where('lang', $toLang)
                        ->where('value', 'like', $prefix . '%')
                        ->pluck('key')
                        ->all();

                    $sourceValues = array_intersect_key($sourceValues, array_flip($untranslatedKeys));
                }

                if (empty($sourceValues)) {
                    return response()->json(['copied' => 0, 'message' => 'No keys to copy.']);
                }

                TranslationService::bulkUpsertPreserveFlag($toLang, $sourceValues);

                return response()->json([
                    'copied' => count($sourceValues),
                    'message' => count($sourceValues) . ' value(s) copied successfully.',
                ]);
            });
        } catch (LockTimeoutException) {
            return response()->json(['error' => 'A copy operation is already in progress. Please wait.'], 429);
        }
    }

    private static function getVersion(): ?string
    {
        return InstalledVersions::getPrettyVersion('agency-orgo/string-translations');
    }

    private function getLocaleForSite(string $handle): ?string
    {
        $site = Site::get($handle);

        return $site?->locale();
    }

    /**
     * Get sites list for tab switcher.
     */
    private function getSites(): array
    {
        return Site::all()->map(fn ($site, $handle) => [
            'handle' => $handle,
            'name' => $site->name(),
            'locale' => $site->locale(),
        ])->values()->all();
    }

    /**
     * If a site has no keys at all, propagate all known keys as untranslated.
     */
    private function propagateKeysIfEmpty(string $site): void
    {
        if (LocalizedString::where('lang', $site)->exists()) {
            return;
        }

        $allKeys = LocalizedString::select('key')
            ->distinct()
            ->pluck('key');

        if ($allKeys->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $allKeys->map(fn ($key) => [
            'key' => $key,
            'lang' => $site,
            'value' => config('string-translations.untranslated_prefix') . $key,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        LocalizedString::insertOrIgnore($rows);
    }

    /**
     * Get fallback sites for a given site
     */
    private function getFallbackSites(string $site): array
    {
        // Define fallback hierarchy
        $fallbacks = [
            'en-gb' => ['en'],
            'en-us' => ['en'],
            'en-rw' => ['en'],
        ];

        return $fallbacks[$site] ?? [];
    }

    /**
     * Get translations with fallback logic
     */
    private function getTranslationsWithFallback(string $primarySite, array $fallbackSites): \Illuminate\Support\Collection
    {
        // Get all possible sites to query (primary + fallbacks)
        $allSites = array_merge([$primarySite], $fallbackSites);

        // Get all translations for these sites
        $allTranslations = LocalizedString::whereIn("lang", $allSites)
            ->orderBy("key")
            ->get()
            ->groupBy('key');

        $result = collect();

        foreach ($allTranslations as $key => $translations) {
            // Find the best translation following the fallback hierarchy
            $bestTranslation = null;

            // First try the primary site
            $primaryTranslation = $translations->where('lang', $primarySite)->first();
            if ($primaryTranslation) {
                $bestTranslation = $primaryTranslation;
            } else {
                // Try fallback sites in order
                foreach ($fallbackSites as $fallbackSite) {
                    $fallbackTranslation = $translations->where('lang', $fallbackSite)->first();
                    if ($fallbackTranslation) {
                        // Create a new record with the primary site but fallback value
                        $bestTranslation = new LocalizedString([
                            'key' => $key,
                            'lang' => $primarySite,
                            'value' => $fallbackTranslation->value
                        ]);
                        break;
                    }
                }
            }

            if ($bestTranslation) {
                $result->push($bestTranslation);
            }
        }

        return $result->sortBy('key');
    }
}
