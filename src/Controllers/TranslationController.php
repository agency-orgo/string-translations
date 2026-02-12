<?php

namespace AgencyOrgo\StringTranslations\Controllers;

use AgencyOrgo\StringTranslations\Models\LocalizedString;
use AgencyOrgo\StringTranslations\Services\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Statamic\Facades\Site;

class TranslationController
{
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

        if (!Schema::hasTable($tableName)) {
            return [
                'translations' => [],
                'activeLang' => $request->get('lang', 'en'),
                'sites' => $this->getSites(),
                'saveUrl' => cp_route('utilities.string-translations'),
                'missingTable' => true,
            ];
        }

        $site = $request->get('lang', 'en');
        $fallbackSites = $this->getFallbackSites($site);
        $data = $this->getTranslationsWithFallback($site, $fallbackSites);

        return [
            'translations' => $data->map(fn ($entry) => [
                'key' => $entry->key,
                'value' => $entry->value,
            ])->values()->all(),
            'activeLang' => $site,
            'sites' => $this->getSites(),
            'saveUrl' => cp_route('utilities.string-translations'),
            'missingTable' => false,
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

    /**
     * Get sites list for tab switcher.
     */
    private function getSites(): array
    {
        return Site::all()->map(fn ($site, $handle) => [
            'handle' => $handle,
            'name' => $site->name(),
        ])->values()->all();
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
