<?php

namespace AgencyOrgo\StringTranslations\Services;

use AgencyOrgo\StringTranslations\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Yaml\Yaml;

/**
 * Under the yaml driver settings live in storage/, not in the synced
 * translations directory. The DeepL key is a secret and must not be committed.
 */
class SettingsService
{
    public const DEEPL_API_KEY = 'deepl_api_key';

    public function get(string $key): ?string
    {
        if ($this->usesFiles()) {
            $value = $this->fileAll()[$key] ?? null;

            return $value !== null ? Crypt::decryptString($value) : null;
        }

        if (! $this->tableExists()) {
            return null;
        }

        $record = Setting::where('key', $key)->first();

        return $record ? Crypt::decryptString($record->value) : null;
    }

    public function set(string $key, ?string $value): void
    {
        if ($this->usesFiles()) {
            $all = $this->fileAll();

            if ($value === null) {
                unset($all[$key]);
            } else {
                $all[$key] = Crypt::encryptString($value);
            }

            $this->fileWrite($all);

            return;
        }

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
        if ($this->usesFiles()) {
            return isset($this->fileAll()[$key]);
        }

        return $this->tableExists() && Setting::where('key', $key)->exists();
    }

    private function usesFiles(): bool
    {
        return config('string-translations.storage.driver') === 'yaml';
    }

    private function tableExists(): bool
    {
        return Schema::hasTable(config('string-translations.database.settings_table', 'string_translation_settings'));
    }

    private function filePath(): string
    {
        return storage_path('string-translations/settings.yaml');
    }

    /**
     * @return array<string, string>  key => encrypted value
     */
    private function fileAll(): array
    {
        $path = $this->filePath();

        return File::exists($path) ? (array) (Yaml::parse(File::get($path)) ?: []) : [];
    }

    private function fileWrite(array $all): void
    {
        $dir = dirname($this->filePath());
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($this->filePath(), Yaml::dump($all, 2, 2));
    }
}
