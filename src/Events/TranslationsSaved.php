<?php

namespace AgencyOrgo\StringTranslations\Events;

use Statamic\Events\Event;

/**
 * Dispatched after one or more translation rows are inserted or updated.
 *
 * Mirrors Statamic's content-event pattern (e.g. `EntrySaved`,
 * `GlobalVariablesSaved`). The addon's ServiceProvider registers a listener
 * that calls Statamic's GraphQL ResponseCache invalidation contract, so
 * frontends sharing this cache pick up changes on the next request.
 */
class TranslationsSaved extends Event
{
    /**
     * @param  string|null  $lang   The locale that was written, or null for
     *                              operations affecting every site (e.g. the
     *                              `createStringTranslations` mutation that
     *                              seeds keys across all sites).
     * @param  array<int, string>  $keys  Keys affected by the write.
     */
    public function __construct(
        public ?string $lang = null,
        public array $keys = [],
    ) {
    }
}
