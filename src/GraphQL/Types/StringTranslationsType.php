<?php

namespace AgencyOrgo\StringTranslations\GraphQL\Types;

use Statamic\Facades\GraphQL;

class StringTranslationsType extends \Rebing\GraphQL\Support\Type
{
    const NAME = 'StringTranslations';

    protected $attributes = [
        'name' => self::NAME,
    ];

    public function fields(): array
    {
        return [
            'lang' => [
                'type' => GraphQL::nonNull(GraphQL::string()),
            ],
            'strings' => [
                'type' => GraphQL::nonNull(GraphQL::type('Array')),
            ],
        ];
    }
}
