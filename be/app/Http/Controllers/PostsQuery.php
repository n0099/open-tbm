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
        $validator = new ParamsValidator((array)Utils::jsonDecode($request->validate([
            'page' => 'integer',
            'query' => 'json'
        ])['query'], true));
        $params = $validator->params;

        $postsIDParams = $params->filter(...Helper::POSTS_ID);
        $isPostIDQuery = $params->count() === \count($postsIDParams) // is there no other params
            && \count($postsIDParams) === 1 // is there only one post id param
            && array_filter($postsIDParams, static fn ($p) => $p->getAllSub() !== []) === []; // is post id param haven't any sub param
        // is fid param exists and there's no other params
        $isFidParamNull = $params->getUniqueParamValue('fid') === null;
        $isFidQuery = !$isFidParamNull && $params->count() === \count($params->filter(...ParamsValidator::UNIQUE_PARAMS_NAME));
        $isIndexQuery = $isPostIDQuery || $isFidQuery;
        $isSearchQuery = !$isIndexQuery;
        if ($isSearchQuery) {
            Helper::abortAPIIf(40002, $isFidParamNull);
        }

        $validator->addDefaultParamsThenValidate();

        if ($isSearchQuery) {
            $query = (new SearchQuery($this->perPageItems))->query($params);
        } else {
            $query = (new IndexQuery($this->perPageItems))->query($params);
        }
        $queryResult = $query->getResult();

        return [
            'pages' => $query->getResultPages(),
            'forum' => ForumModel::where('fid', $queryResult['fid'])->hidePrivateFields()->first()?->toArray(),
            'threads' => $query->toNestedPosts(),
            'users' => UserModel::whereIn(
                'uid',
                collect([
                    array_column($queryResult['thread'], 'authorUid'),
                    array_column($queryResult['thread'], 'latestReplierUid'),
                    array_column($queryResult['reply'], 'authorUid'),
                    array_column($queryResult['subReply'], 'authorUid')
                ])->flatten()->unique()->sort()->toArray()
            )->hidePrivateFields()->get()->toArray()
        ];
    }
}
