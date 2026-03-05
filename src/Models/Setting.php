<?php

namespace AgencyOrgo\StringTranslations\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    public function getTable()
    {
        return config('string-translations.database.settings_table', 'string_translation_settings');
    }

    public function getConnectionName()
    {
        $connection = config('string-translations.database.connection', 'default');

        return $connection === 'default' ? null : $connection;
    }
}
