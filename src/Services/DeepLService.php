<?php

namespace AgencyOrgo\StringTranslations\Services;

use Illuminate\Support\Facades\Http;

class DeepLService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = str_ends_with($apiKey, ':fx')
            ? 'https://api-free.deepl.com/v2'
            : 'https://api.deepl.com/v2';
    }

    /**
     * Translate an array of texts from source to target language.
     *
     * @return array Translated texts in the same order
     */
    public function translate(string $sourceLang, string $targetLang, array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $results = [];

        foreach (array_chunk($texts, 50) as $chunk) {
            $response = Http::withHeaders([
                'Authorization' => 'DeepL-Auth-Key ' . $this->apiKey,
            ])->post($this->baseUrl . '/translate', [
                'text' => array_values($chunk),
                'source_lang' => $this->toDeepLCode($sourceLang, isTarget: false),
                'target_lang' => $this->toDeepLCode($targetLang, isTarget: true),
            ]);

            if (! $response->successful()) {
                $status = $response->status();
                $body = $response->body();
                throw new \RuntimeException("DeepL API error ({$status}): {$body}");
            }

            foreach ($response->json('translations', []) as $translation) {
                $results[] = $translation['text'];
            }
        }

        return $results;
    }

    /**
     * Convert a Statamic locale (e.g. de_DE, en_US, zh_CN) to a DeepL language code.
     *
     * DeepL source_lang: always the base language (EN, DE, FR, ZH).
     * DeepL target_lang: base language for most, but regional variants required for EN, PT, ZH, ES-419.
     */
    private function toDeepLCode(string $locale, bool $isTarget): string
    {
        // Normalize: en_US -> en-us, zh_CN -> zh-cn
        $normalized = strtolower(str_replace('_', '-', $locale));

        // Explicit target mappings where DeepL expects non-obvious codes
        $targetMap = [
            'zh-cn' => 'ZH-HANS',
            'zh-hans' => 'ZH-HANS',
            'zh-tw' => 'ZH-HANT',
            'zh-hant' => 'ZH-HANT',
            'zh' => 'ZH-HANS',
            'en' => 'EN-US',
            'en-us' => 'EN-US',
            'en-gb' => 'EN-GB',
            'pt' => 'PT-PT',
            'pt-pt' => 'PT-PT',
            'pt-br' => 'PT-BR',
            'es-419' => 'ES-419',
            'nb' => 'NB',
            'no' => 'NB',
        ];

        // Source language must always be base code (no regional variant)
        $sourceMap = [
            'zh-cn' => 'ZH',
            'zh-hans' => 'ZH',
            'zh-tw' => 'ZH',
            'zh-hant' => 'ZH',
            'en-us' => 'EN',
            'en-gb' => 'EN',
            'pt-br' => 'PT',
            'pt-pt' => 'PT',
            'nb' => 'NB',
            'no' => 'NB',
        ];

        if ($isTarget && isset($targetMap[$normalized])) {
            return $targetMap[$normalized];
        }

        if (! $isTarget && isset($sourceMap[$normalized])) {
            return $sourceMap[$normalized];
        }

        // Default: uppercase the base language
        $parts = explode('-', $normalized);

        return strtoupper($parts[0]);
    }
}
