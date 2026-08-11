<?php

namespace AgencyOrgo\StringTranslations\GraphQL\Mutations;

use AgencyOrgo\StringTranslations\Contracts\TranslationRepository;
use AgencyOrgo\StringTranslations\Events\TranslationsSaved;
use AgencyOrgo\StringTranslations\GraphQL\Types\CreateStringTranslationsResultType;
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
        $prefix = config('string-translations.untranslated_prefix');
        $repo = app(TranslationRepository::class);

        $pairs = [];
        foreach ($keys as $key) {
            $pairs[$key] = $prefix . $key;
        }

        $created = 0;
        foreach (Site::all()->keys()->all() as $handle) {
            $created += $repo->insertMissing($handle, $pairs);
        }

        if ($created > 0) {
            TranslationsSaved::dispatch(null, $keys);
        }

        return ['created' => $created];
    }
}
