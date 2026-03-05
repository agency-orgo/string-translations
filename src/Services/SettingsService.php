<?php

namespace AgencyOrgo\StringTranslations\Services;

use AgencyOrgo\StringTranslations\Models\Setting;
use Illuminate\Support\Facades\Crypt;

class SettingsService
{
    public const DEEPL_API_KEY = 'deepl_api_key';

    public function get(string $key): ?string
    {
        $record = Setting::where('key', $key)->first();

        if (! $record) {
            return null;
        }

        return Crypt::decryptString($record->value);
    }

    public function set(string $key, ?string $value): void
    {
        if ($value === null) {
            Setting::where('key', $key)->delete();

            return;
        }

        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => Crypt::encryptString($value)]
        );
    }

    public function has(string $key): bool
    {
        return Setting::where('key', $key)->exists();
    }
}
