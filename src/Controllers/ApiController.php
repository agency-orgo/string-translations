<?php

namespace AgencyOrgo\StringTranslations\Controllers;

use AgencyOrgo\StringTranslations\Models\LocalizedString;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Facades\Site;

class ApiController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => 'required|string|max:10',
        ]);

        $lang = $request->get('lang');

        $strings = LocalizedString::where('lang', $lang)
            ->orderBy('key')
            ->pluck('value', 'key');

        return response()->json([
            'lang' => $lang,
            'strings' => $strings,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keys' => 'required|array|min:1',
            'keys.*' => 'string|max:255',
        ]);

        $sites = Site::all()->keys()->all();
        $now = now();

        $rows = [];
        foreach ($validated['keys'] as $key) {
            foreach ($sites as $handle) {
                $rows[] = [
                    'key' => $key,
                    'lang' => $handle,
                    'value' => 'untranslated_' . $key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $created = LocalizedString::insertOrIgnore($rows);

        return response()->json([
            'created' => $created,
        ], 201);
    }
}
