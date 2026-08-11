<?php

namespace AgencyOrgo\StringTranslations\GraphQL\Queries;

use AgencyOrgo\StringTranslations\Contracts\TranslationRepository;
use AgencyOrgo\StringTranslations\GraphQL\Types\StringTranslationsType;
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

        $strings = [];
        foreach (app(TranslationRepository::class)->forLang($lang) as $key => $info) {
            $strings[$key] = $info['value'];
        }

        return [
            'lang' => $lang,
            'strings' => $strings,
        ];
    }
}
