<?php

namespace AgencyOrgo\StringTranslations\Repositories;

use AgencyOrgo\StringTranslations\Contracts\TranslationRepository;
use AgencyOrgo\StringTranslations\Models\LocalizedString;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseTranslationRepository implements TranslationRepository
{
    public function transaction(callable $callback): void
    {
        DB::transaction($callback);
    }

    public function forLang(string $lang): array
    {
        return LocalizedString::where('lang', $lang)
            ->orderBy('key')
            ->get(['key', 'value', 'auto_translated'])
            ->mapWithKeys(fn ($row) => [$row->key => [
                'value' => $row->value,
                'auto' => (bool) $row->auto_translated,
            ]])
            ->all();
    }

    public function allKeys(): array
    {
        return LocalizedString::select('key')->distinct()->pluck('key')->all();
    }

    public function langHasKeys(string $lang): bool
    {
        return LocalizedString::where('lang', $lang)->exists();
    }

    public function upsert(string $lang, array $pairs, ?bool $autoTranslated): void
    {
        if (empty($pairs)) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($pairs as $key => $value) {
            $row = ['key' => $key, 'lang' => $lang, 'value' => $value, 'created_at' => $now, 'updated_at' => $now];
            if ($autoTranslated !== null) {
                $row['auto_translated'] = $autoTranslated;
            }
            $rows[] = $row;
        }

        $update = $autoTranslated !== null
            ? ['value', 'auto_translated', 'updated_at']
            : ['value', 'updated_at'];

        try {
            LocalizedString::upsert($rows, ['key', 'lang'], $update);
        } catch (\Exception $e) {
            report($e);

            foreach ($pairs as $key => $value) {
                $attributes = ['value' => $value, 'updated_at' => $now];
                if ($autoTranslated !== null) {
                    $attributes['auto_translated'] = $autoTranslated;
                }
                LocalizedString::updateOrCreate(['key' => $key, 'lang' => $lang], $attributes);
            }
        }
    }

    public function insertMissing(string $lang, array $pairs): int
    {
        if (empty($pairs)) {
            return 0;
        }

        $now = now();
        $rows = [];
        foreach ($pairs as $key => $value) {
            $rows[] = ['key' => $key, 'lang' => $lang, 'value' => $value, 'created_at' => $now, 'updated_at' => $now];
        }

        return LocalizedString::insertOrIgnore($rows);
    }

    public function deleteKeys(array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        LocalizedString::whereIn('key', $keys)->delete();

        return array_values($keys);
    }

    public function isAvailable(): bool
    {
        return Schema::hasTable(config('string-translations.database.table', 'localized_strings'));
    }
}
