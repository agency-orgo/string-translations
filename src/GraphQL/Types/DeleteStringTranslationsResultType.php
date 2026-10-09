<?php

namespace AgencyOrgo\StringTranslations\GraphQL\Types;

use Statamic\Facades\GraphQL;

class DeleteStringTranslationsResultType extends \Rebing\GraphQL\Support\Type
{
    const NAME = 'DeleteStringTranslationsResult';

    protected $attributes = [
        'name' => self::NAME,
    ];

    public function fields(): array
    {
        return [
            'deleted' => [
                'type' => GraphQL::nonNull(GraphQL::listOf(GraphQL::nonNull(GraphQL::string()))),
            ],
        ];
    }
}
