<?php

namespace AgencyOrgo\StringTranslations\GraphQL\Mutations;

use AgencyOrgo\StringTranslations\Contracts\TranslationRepository;
use AgencyOrgo\StringTranslations\Events\TranslationsDeleted;
use AgencyOrgo\StringTranslations\GraphQL\Types\DeleteStringTranslationsResultType;
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
     * Uses the addon's own token rather than Statamic's endpoint-wide one, so
     * sites can keep read queries public while deletion stays server-side.
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

        // deleteKeys() echoes back whatever it is given, so narrow to keys that
        // exist first; the response and the event then report real removals.
        $repo->transaction(function () use ($repo, $args, &$deleted) {
            $deleted = array_values(array_intersect(array_unique($args['keys']), $repo->allKeys()));
            $repo->deleteKeys($deleted);
        });

        // Skipping the event when nothing matched avoids an empty git commit
        // under the yaml driver.
        if (! empty($deleted)) {
            TranslationsDeleted::dispatch($deleted);
        }

        return ['deleted' => $deleted];
    }
}
