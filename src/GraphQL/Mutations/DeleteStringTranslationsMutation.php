<?php

namespace AgencyOrgo\StringTranslations\GraphQL\Mutations;

use AgencyOrgo\StringTranslations\Contracts\TranslationRepository;
use AgencyOrgo\StringTranslations\Events\TranslationsDeleted;
use AgencyOrgo\StringTranslations\GraphQL\Types\DeleteStringTranslationsResultType;
use AgencyOrgo\StringTranslations\Services\TranslationService;
use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Mutation;
use Statamic\Facades\GraphQL;

class DeleteStringTranslationsMutation extends Mutation
{
    public const TOKEN_HEADER = 'X-String-Translations-Token';

    protected $attributes = [
        'name' => 'deleteStringTranslations',
    ];

    public function type(): Type
    {
        return GraphQL::type(DeleteStringTranslationsResultType::NAME);
    }

    public function args(): array
    {
        return [
            'keys' => [
                'type' => GraphQL::nonNull(GraphQL::listOf(GraphQL::nonNull(GraphQL::string()))),
            ],
        ];
    }

    /**
     * Own token instead of Statamic's endpoint-wide one, so reads can stay public.
     */
    public function authorize($root, array $args, $ctx, ?ResolveInfo $resolveInfo = null, ?Closure $getSelectFields = null): bool
    {
        $token = (string) config('string-translations.graphql.delete_token');

        return $token !== ''
            && hash_equals($token, (string) request()->header(self::TOKEN_HEADER));
    }

    public function resolve($root, $args)
    {
        $repo = app(TranslationRepository::class);
        $deleted = [];

        $repo->transaction(function () use ($repo, $args, &$deleted) {
            $deleted = $repo->deleteKeys(TranslationService::normalizeKeys($args['keys']));
        });

        if (! empty($deleted)) {
            TranslationsDeleted::dispatch($deleted);
        }

        return ['deleted' => $deleted];
    }
}
