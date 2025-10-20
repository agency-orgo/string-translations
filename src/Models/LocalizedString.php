<?php

namespace AgencyOrgo\StringTranslations\Models;

use Illuminate\Database\Eloquent\Model;

class LocalizedString extends Model
{
    protected $guarded = [];

    protected $table;

    public function __construct(array $attributes = [])
    {
        $this->table = config('string-translations.database.table', 'localized_strings');
        parent::__construct($attributes);
    }

    public function getConnectionName()
    {
        return config('string-translations.database.connection', 'default');
    }
}