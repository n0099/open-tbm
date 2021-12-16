<?php

namespace App\Http\Controllers;

use App\Helper;
use App\Http\PostsQuery\IndexQuery;
use App\Http\PostsQuery\SearchQuery;
use App\Http\PostsQuery\ParamsValidator;
use App\Tieba\Eloquent\ForumModel;
use App\Tieba\Eloquent\UserModel;
use GuzzleHttp\Utils;

class PostsQuery extends Controller
{
    private int $perPageItems = 200;

    public function query(\Illuminate\Http\Request $request): array
    {
        $validator = new ParamsValidator((array)Utils::jsonDecode(
            $request->validate([
                'page' => 'integer',
                'query' => 'json'
            ])['query'],
            true
        ));
        $params = $validator->params;

        $postsIDParams = $params->filter(...Helper::POSTS_ID);
        $isPostIDQuery = // is there no other params, note we ignored the existence of unique params
            \count($params->omit('postTypes')) === \count($postsIDParams)
            && \count($postsIDParams) === 1 // is there only one post id param
            && array_filter($postsIDParams, static fn ($p) => $p->getAllSub() !== []) === []; // is post id param haven't any sub param
        // is the fid param exists and there's no other params
        $isFidParamNull = $params->getUniqueParamValue('fid') === null;
        $isFidQuery = !$isFidParamNull && $params->count() === \count($params->filter(...ParamsValidator::UNIQUE_PARAMS_NAME));
        $isIndexQuery = $isPostIDQuery || $isFidQuery;
        $isSearchQuery = !$isIndexQuery;
        if ($isSearchQuery) {
            Helper::abortAPIIf(40002, $isFidParamNull);
        }

        $validator->addDefaultParamsThenValidate();

        $queryClass = $isIndexQuery ? IndexQuery::class : SearchQuery::class;
        $query = (new $queryClass($this->perPageItems))->query($params);
        $queryResult = $query->fillWithParentPost();

        return [
            'pages' => $query->getResultPages(),
            'forum' => ForumModel::where('fid', $queryResult['fid'])->hidePrivateFields()->first()?->toArray(),
            'threads' => $query::nestPostsWithParent(...$queryResult),
            'users' => UserModel::whereIn(
                'uid',
                collect([
                    array_column($queryResult['threads'], 'latestReplierUid'),
                    array_map(
                        static fn ($type) => array_column($queryResult[$type], 'authorUid'),
                        Helper::POST_TYPES_PLURAL
                    ),
                ])->flatten()->unique()->sort()->toArray()
            )->hidePrivateFields()->get()->toArray()
        ];
    }
}
