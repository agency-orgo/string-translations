<?php

namespace AgencyOrgo\StringTranslations\Controllers;

use AgencyOrgo\StringTranslations\Contracts\TranslationRepository;
use AgencyOrgo\StringTranslations\Events\TranslationsSaved;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Facades\Site;

class ApiController
{
    public function __construct(
        private readonly TranslationRepository $repo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => 'required|string|max:10',
        ]);

        $lang = $request->get('lang');

        $strings = [];
        foreach ($this->repo->forLang($lang) as $key => $info) {
            $strings[$key] = $info['value'];
        }

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

        $prefix = config('string-translations.untranslated_prefix');

        $pairs = [];
        foreach ($validated['keys'] as $key) {
            $pairs[$key] = $prefix . $key;
        }

        $created = 0;
        foreach (Site::all()->keys()->all() as $handle) {
            $created += $this->repo->insertMissing($handle, $pairs);
        }

        if ($created > 0) {
            TranslationsSaved::dispatch(null, $validated['keys']);
        }

        return response()->json([
            'created' => $created,
        ], 201);
    }
}
