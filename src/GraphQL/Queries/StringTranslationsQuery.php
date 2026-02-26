<?php

namespace AgencyOrgo\StringTranslations\GraphQL\Queries;

use AgencyOrgo\StringTranslations\GraphQL\Types\StringTranslationsType;
use AgencyOrgo\StringTranslations\Models\LocalizedString;
use GraphQL\Type\Definition\Type;
use Statamic\Facades\GraphQL;
use Statamic\GraphQL\Queries\Query;

class StringTranslationsQuery extends Query
{
    protected $attributes = [
        'name' => 'string_translations',
    ];

    public function type(): Type
    {
        return GraphQL::type(StringTranslationsType::NAME);
    }

    public function args(): array
    {
        return [
            'lang' => [
                'type' => GraphQL::nonNull(GraphQL::string()),
            ],
        ];
    }

    public function resolve($root, $args)
    {
        $lang = $args['lang'];

        $strings = LocalizedString::where('lang', $lang)
            ->orderBy('key')
            ->pluck('value', 'key')
            ->all();

        return [
            'lang' => $lang,
            'strings' => $strings,
        ];
    }
}
