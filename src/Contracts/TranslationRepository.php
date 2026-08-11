<?php

namespace AgencyOrgo\StringTranslations\Contracts;

interface TranslationRepository
{
    /**
     * Run the callback so a save and its deletions land together or not at all.
     */
    public function transaction(callable $callback): void;

    /**
     * All translations for a locale, ordered by key.
     *
     * @return array<string, array{value: string, auto: bool}>
     */
    public function forLang(string $lang): array;

    /**
     * Distinct keys across every locale.
     *
     * @return array<int, string>
     */
    public function allKeys(): array;

    public function langHasKeys(string $lang): bool;

    /**
     * @param  array<string, string>  $pairs
     * @param  bool|null  $autoTranslated  Null preserves the existing flag.
     */
    public function upsert(string $lang, array $pairs, ?bool $autoTranslated): void;

    /**
     * Insert pairs that don't already exist for the locale, leaving existing
     * ones untouched.
     *
     * @param  array<string, string>  $pairs
     * @return int  Number of pairs created.
     */
    public function insertMissing(string $lang, array $pairs): int;

    /**
     * Remove the given keys from every locale.
     *
     * @param  array<int, string>  $keys
     * @return array<int, string>  The keys that were actually targeted.
     */
    public function deleteKeys(array $keys): array;

    /**
     * Whether the store is ready to use. The database driver needs its
     * migrations run, the yaml driver creates its directory on demand.
     */
    public function isAvailable(): bool;
}
