<?php

namespace App\Http\Controllers;

use App\Helper;
use App\Http\PostsQuery\IndexQuery;
use App\Http\PostsQuery\ParamsValidator;
use App\Http\PostsQuery\SearchQuery;
use App\Eloquent\Model\Forum;
use App\Eloquent\Model\User;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;

class PostsQuery extends Controller
{
    public function query(\Illuminate\Http\Request $request): array
    {
        $validator = new ParamsValidator(Helper::jsonDecode(
            $request->validate([
                'cursor' => [ // https://stackoverflow.com/questions/475074/regex-to-parse-or-validate-base64-data
                    // (,|$)|,){5,6} means allow at most five or six components of base64 segment or empty string to exist
                    'regex:/^(([A-Za-z0-9-_]{4})*([A-Za-z0-9-_]{2,3})(,|$)|,){5,6}$/'
                ],
                'query' => 'json|required'
            ])['query'],
        ));
        $params = $validator->params;

        $postIDParams = $params->pick(...Helper::POST_ID);
        $isQueryByPostID =
            // is there no other params except unique params and post ID params
            \count($params->omit(...ParamsValidator::UNIQUE_PARAMS_NAME, ...Helper::POST_ID)) === 0
            // is there only one post ID param
            && \count($postIDParams) === 1
            // is all post ID params doesn't own any sub param
            && array_filter($postIDParams, static fn ($p) => $p->getAllSub() !== []) === [];
        $isFidParamNull = $params->getUniqueParamValue('fid') === null;
        // is the fid param exists and there's no other params except unique params
        $isQueryByFid = !$isFidParamNull && \count($params->omit(...ParamsValidator::UNIQUE_PARAMS_NAME)) === 0;
        $isIndexQuery = $isQueryByPostID || $isQueryByFid;
        $isSearchQuery = !$isIndexQuery;
        Helper::abortAPIIf(40002, $isSearchQuery && $isFidParamNull);

        $validator->addDefaultParamsThenValidate($isIndexQuery);

        $queryClass = $isIndexQuery ? IndexQuery::class : SearchQuery::class;
        Debugbar::startMeasure('$queryClass->query()');
        $query = (new $queryClass())->query($params, $request->get('cursor'));
        Debugbar::stopMeasure('$queryClass->query()');
        Debugbar::startMeasure('fillWithParentPost');
        $result = $query->fillWithParentPost();
        Debugbar::stopMeasure('fillWithParentPost');

        Debugbar::startMeasure('queryUsers');
        $whereCurrentFid = static fn (HasOne $q) => $q->where('fid', $result['fid']);
        $users = User::whereIn(
            'uid',
            $result['threads']
                ->pluck('latestReplierUid')
                ->concat(array_map(
                    static fn (string $type) => $result[$type]->pluck('authorUid'),
                    Helper::POST_TYPES_PLURAL
                ))
                ->flatten()->filter() // remove NULLs from column latestReplierUid
                ->unique()->toArray()
        )->with(['currentForumModerator' => $whereCurrentFid, 'currentAuthorExpGrade' => $whereCurrentFid])
            ->selectPublicFields()->get()->toArray();
        Debugbar::stopMeasure('queryUsers');

        return [
            'type' => $isIndexQuery ? 'index' : 'search',
            'pages' => [
                ...$query->getResultPages(),
                ...Arr::except($result, ['fid', ...Helper::POST_TYPES_PLURAL])
            ],
            'forum' => Forum::fid($result['fid'])->selectPublicFields()->first()?->toArray(),
            'threads' => $query->reOrderNestedPosts($query::nestPostsWithParent(...$result)),
            'users' => $users
        ];
    }
}
