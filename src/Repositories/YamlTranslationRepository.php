<?php

namespace AgencyOrgo\StringTranslations\Repositories;

use AgencyOrgo\StringTranslations\Contracts\TranslationRepository;
use AgencyOrgo\StringTranslations\Support\YamlTranslationStore;

class YamlTranslationRepository implements TranslationRepository
{
    public function __construct(private readonly YamlTranslationStore $store) {}

    public function transaction(callable $callback): void
    {
        $this->store->lock($callback);
    }

    public function forLang(string $lang): array
    {
        $values = $this->store->read($lang);
        ksort($values);
        $auto = $this->store->meta()[$lang] ?? [];

        $result = [];
        foreach ($values as $key => $value) {
            $result[$key] = ['value' => $value, 'auto' => ! empty($auto[$key])];
        }

        return $result;
    }

    public function allKeys(): array
    {
        $keys = [];
        foreach ($this->store->locales() as $lang) {
            $keys = array_merge($keys, array_keys($this->store->read($lang)));
        }

        return array_values(array_unique($keys));
    }

    public function langHasKeys(string $lang): bool
    {
        return ! empty($this->store->read($lang));
    }

    public function upsert(string $lang, array $pairs, ?bool $autoTranslated): void
    {
        if (empty($pairs)) {
            return;
        }

        $this->store->lock(function () use ($lang, $pairs, $autoTranslated) {
            $values = $this->store->read($lang);
            $meta = $this->store->meta();

            foreach ($pairs as $key => $value) {
                $values[$key] = $value;

                if ($autoTranslated === true) {
                    $meta[$lang][$key] = true;
                } elseif ($autoTranslated === false) {
                    unset($meta[$lang][$key]);
                }
            }

            $this->store->write($lang, $values);
            $this->store->writeMeta($meta);
        });
    }

    public function insertMissing(string $lang, array $pairs): int
    {
        if (empty($pairs)) {
            return 0;
        }

        return $this->store->lock(function () use ($lang, $pairs) {
            $values = $this->store->read($lang);

            $created = 0;
            foreach ($pairs as $key => $value) {
                if (! array_key_exists($key, $values)) {
                    $values[$key] = $value;
                    $created++;
                }
            }

            if ($created > 0) {
                $this->store->write($lang, $values);
            }

            return $created;
        });
    }

    public function deleteKeys(array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        return $this->store->lock(function () use ($keys) {
            $flip = array_flip($keys);
            $meta = $this->store->meta();

            foreach ($this->store->locales() as $lang) {
                $values = $this->store->read($lang);
                $remaining = array_diff_key($values, $flip);

                if (count($remaining) !== count($values)) {
                    $this->store->write($lang, $remaining);
                }

                if (isset($meta[$lang])) {
                    $meta[$lang] = array_diff_key($meta[$lang], $flip);
                }
            }

            $this->store->writeMeta($meta);

            return array_values($keys);
        });
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
