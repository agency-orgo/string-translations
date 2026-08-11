<?php

namespace AgencyOrgo\StringTranslations\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Flat-file persistence for the yaml driver. One sorted `key: value` file per
 * locale at `{path}/{lang}.yaml`, plus a `{path}/.meta.yaml` sidecar holding
 * the auto_translated flags as `lang -> {key: true}`.
 */
class YamlTranslationStore
{
    private string $path;

    /** @var array<string, array<string, string>> */
    private array $cache = [];

    /** @var array<string, array<string, bool>>|null */
    private ?array $meta = null;

    private int $lockDepth = 0;

    public function __construct(string $path)
    {
        $this->path = rtrim($path, '/');
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, string>
     */
    public function read(string $lang): array
    {
        if (isset($this->cache[$lang])) {
            return $this->cache[$lang];
        }

        $file = $this->file($lang);
        if (! File::exists($file)) {
            return $this->cache[$lang] = [];
        }

        return $this->cache[$lang] = (array) (Yaml::parse(File::get($file)) ?: []);
    }

    /**
     * @param  array<string, string>  $pairs
     */
    public function write(string $lang, array $pairs): void
    {
        ksort($pairs);

        $this->ensureDir();
        $this->atomicPut($this->file($lang), Yaml::dump($pairs, 2, 2));
        $this->cache[$lang] = $pairs;
    }

    /**
     * Locales that currently have a file on disk.
     *
     * @return array<int, string>
     */
    public function locales(): array
    {
        if (! File::isDirectory($this->path)) {
            return [];
        }

        return collect(File::glob($this->path.'/*.yaml'))
            ->map(fn ($file) => pathinfo($file, PATHINFO_FILENAME))
            ->all();
    }

    /**
     * @return array<string, array<string, bool>>  lang => {key: true}
     */
    public function meta(): array
    {
        if ($this->meta !== null) {
            return $this->meta;
        }

        $file = $this->metaFile();
        if (! File::exists($file)) {
            return $this->meta = [];
        }

        return $this->meta = (array) (Yaml::parse(File::get($file)) ?: []);
    }

    /**
     * @param  array<string, array<string, bool>>  $meta
     */
    public function writeMeta(array $meta): void
    {
        $meta = array_filter($meta, fn ($keys) => ! empty($keys));

        $this->ensureDir();

        $file = $this->metaFile();
        if (empty($meta)) {
            File::delete($file);
        } else {
            $this->atomicPut($file, Yaml::dump($meta, 4, 2));
        }

        $this->meta = $meta;
    }

    /**
     * Run a mutating closure under an exclusive lock so concurrent writes don't
     * interleave read-modify-write cycles on the same files. Re-entrant: the
     * repository wraps whole saves in this, and each write inside locks again.
     */
    public function lock(callable $callback): mixed
    {
        if ($this->lockDepth > 0) {
            return $callback();
        }

        return Cache::lock('string-translations:yaml-store', 30)->block(10, function () use ($callback) {
            // Anything read before the lock may be stale now.
            $this->cache = [];
            $this->meta = null;
            $this->lockDepth++;

            try {
                return $callback();
            } finally {
                $this->lockDepth--;
            }
        });
    }

    private function file(string $lang): string
    {
        return $this->path."/{$lang}.yaml";
    }

    private function metaFile(): string
    {
        return $this->path.'/.meta.yaml';
    }

    private function ensureDir(): void
    {
        if (! File::isDirectory($this->path)) {
            File::makeDirectory($this->path, 0755, true);
        }
    }

    private function atomicPut(string $file, string $contents): void
    {
        $tmp = $file.'.'.bin2hex(random_bytes(4)).'.tmp';
        File::put($tmp, $contents);
        rename($tmp, $file);
    }
}
