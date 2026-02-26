<?php

namespace AgencyOrgo\StringTranslations\GraphQL\Mutations;

use AgencyOrgo\StringTranslations\GraphQL\Types\CreateStringTranslationsResultType;
use AgencyOrgo\StringTranslations\Models\LocalizedString;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Mutation;
use Statamic\Facades\GraphQL;
use Statamic\Facades\Site;

class CreateStringTranslationsMutation extends Mutation
{
    protected $attributes = [
        'name' => 'createStringTranslations',
    ];

    public function type(): Type
    {
        return GraphQL::type(CreateStringTranslationsResultType::NAME);
    }

    public function args(): array
    {
        return [
            'keys' => [
                'type' => GraphQL::nonNull(GraphQL::listOf(GraphQL::nonNull(GraphQL::string()))),
            ],
        ];
    }

    public function resolve($root, $args)
    {
        $keys = $args['keys'];
        $sites = Site::all()->keys()->all();
        $now = now();

        $rows = [];
        foreach ($keys as $key) {
            foreach ($sites as $handle) {
                $rows[] = [
                    'key' => $key,
                    'lang' => $handle,
                    'value' => 'untranslated_'.$key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $created = LocalizedString::insertOrIgnore($rows);

        return ['created' => $created];
    }
}
