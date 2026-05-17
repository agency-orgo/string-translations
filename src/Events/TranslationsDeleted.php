<?php

namespace AgencyOrgo\StringTranslations\Events;

use Statamic\Events\Event;

/**
 * Dispatched after one or more translation keys are removed across every
 * locale (the addon's only delete path).
 *
 * Mirrors Statamic's content-event pattern (e.g. `EntryDeleted`). The addon's
 * ServiceProvider registers a listener that calls Statamic's GraphQL
 * ResponseCache invalidation contract so frontends pick up the removal on
 * their next request.
 */
class TranslationsDeleted extends Event
{
    /**
     * @param  array<int, string>  $keys  Keys removed from every locale.
     */
    public function __construct(public array $keys = [])
    {
    }
}
