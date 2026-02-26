<?php

namespace AgencyOrgo\StringTranslations\GraphQL\Types;

use Statamic\Facades\GraphQL;

class CreateStringTranslationsResultType extends \Rebing\GraphQL\Support\Type
{
    const NAME = 'CreateStringTranslationsResult';

    protected $attributes = [
        'name' => self::NAME,
    ];

    public function fields(): array
    {
        return [
            'created' => [
                'type' => GraphQL::nonNull(GraphQL::int()),
            ],
        ];
    }
}
