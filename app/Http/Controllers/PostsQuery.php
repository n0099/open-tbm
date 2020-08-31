<?php

namespace App\Http\Controllers;

use App\Tieba\Eloquent\IndexModel;
use App\Helper;
use App\Tieba\Eloquent\ForumModel;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\Eloquent\UserModel;
use App\Tieba\Post\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class PostsQuery extends Controller
{
    private int $pagingPerPageItems = 200;

    public function query(\Illuminate\Http\Request $request): array
    {
        /**
         * @param array|string $names
         * @return array
         */
        $filterParams = function ($names) use (&$queryParams): array { // cannot use one-liner fn() => syntax here since $queryParams won't sync changes
            return array_values(array_filter($queryParams, fn(array $param): bool => \in_array(array_keys($param)[0], (array)$names, true)));
        }; // array_values() will remove keys remained by array_filter() from original $queryParams
        /**
         * @param string $name
         * @return mixed|null
         */
        $getParamValue = fn(string $name) => $filterParams($name)[0][$name] ?? null;
        /**
         * @param string $name
         * @param mixed $value
         * @throws \InvalidArgumentException
         */
        $setParamValue = function (string $name, $value) use (&$queryParams): void {
            $filteredParams = array_keys(array_filter($queryParams, fn(array $param): bool => array_keys($param)[0] === $name));
            if ($filteredParams === []) {
                throw new \InvalidArgumentException('Cannot find param with given param name');
            }
            $queryParams[$filteredParams[0]][$name] = $value; // only set first param's value which occurs in $queryParams
        };

        $queryParams = json_decode($request->validate(['page' => 'integer', 'query' => 'json'])['query'], true, 512, JSON_THROW_ON_ERROR);
        $paramsValidValue = [
            'userGender' => [0, 1, 2],
            'userManagerType' => ['NULL', 'manager', 'assist', 'voiceadmin']
        ];
        $dateRangeValidator = function (string $attribute, string $value, \Closure $fail) {
            \Validator::make(explode(',', $value), ['0' => 'date|before_or_equal:1', '1' => 'date|after_or_equal:0'])->validate();
        };
        \Validator::make($queryParams, [ // note we haven't validate is sub param have corresponding main param yet
            '*.fid' => 'integer',
            '*.postTypes' => 'array|in:thread,reply,subReply',
            '*.orderBy' => 'string|in:postTime,tid,pid,spid',
            '*.direction' => 'in:ASC,DESC',
            '*.tid' => 'integer',
            '*.pid' => 'integer',
            '*.spid' => 'integer',
            '*.postTime' => $dateRangeValidator,
            '*.latestReplyTime' => $dateRangeValidator,
            '*.threadViewNum' => 'integer',
            '*.threadShareNum' => 'integer',
            '*.threadReplyNum' => 'integer',
            '*.replySubReplyNum' => 'integer',
            '*.threadProperties' => 'array|in:good,sticky',
            '*.authorUid' => 'integer',
            '*.authorExpGrade' => 'integer',
            '*.authorGender' => Rule::in($paramsValidValue['userGender']),
            '*.authorManagerType' => Rule::in($paramsValidValue['userManagerType']),
            '*.latestReplierUid' => 'integer',
            '*.latestReplierGender' => Rule::in($paramsValidValue['userGender']),
            // sub param of tid, pid, spid, threadViewNum, threadShareNum, threadReplyNum, replySubReplyNum, authorUid, authorExpGrade, latestReplierUid
            '*.range' => 'in:<,=,>',
            // sub param of threadTitle, postContent, authorName, authorDisplayName, latestReplierName, latestReplierDisplayName
            '*.matchBy' => 'in:implicit,explicit',
            '*.spaceSplit' => 'boolean'
        ])->validate();

        Helper::abortAPIIf(40001, \count($queryParams) === \count($filterParams(['postTypes', 'orderBy']))); // only fill postTypes and/or orderBy uniqueParam doesn't query anything
        $uniqueParamsName = ['fid', 'postTypes', 'orderBy'];
        $postIDParamsName = ['tid', 'pid', 'spid'];
        $isPostIDQuery = \count($queryParams) === \count($filterParams([...$uniqueParamsName, ...$postIDParamsName])) // is there no other params
            && \count($filterParams($postIDParamsName)) === 1 // is there only one post id param
            && array_column($queryParams, 'range') === []; // is post id param haven't any sub param
        $isFidQuery = $getParamValue('fid') !== null && \count($queryParams) === \count($filterParams($uniqueParamsName)); // is fid unique param exists and there's no other params
        $isIndexQuery = $isPostIDQuery || $isFidQuery;
        $isSearchQuery = false;
        if (! $isIndexQuery) {
            Helper::abortAPIIf(40002, $getParamValue('fid') === null);
            $isSearchQuery = ! $isIndexQuery;
        }

        foreach ($uniqueParamsName as $uniqueParamName) {
            Helper::abortAPIIf(40005, \count($filterParams($uniqueParamName)) > 1); // is same unique param only appeared once
        }
        $uniqueParamsDefaultValue = [
            'postTypes' => ['value' => ['thread', 'reply', 'subReply']],
            'orderBy' => ['value' => 'default', 'subParam' => ['direction' => 'default']]
        ];
        foreach ($uniqueParamsDefaultValue as $uniqueParamName => $uniqueParamDefaultValue) {
            if ($getParamValue($uniqueParamName) === null) { // add unique params with default value when it's not presented
                $queryParams[] = array_merge([$uniqueParamName => $uniqueParamDefaultValue['value']], $uniqueParamDefaultValue['subParam'] ?? []);
            }
        }
        $setParamValue('postTypes', array_sort($getParamValue('postTypes'))); // sort here to prevent further sort while validating

        $numericParamsDefaultValue = ['range' => '='];
        $textMatchParamsDefaultValue = ['matchBy' => 'implicit', 'spaceSplit' => false];
        $subParamsDefaultValue = [
            'tid' => $numericParamsDefaultValue,
            'pid' => $numericParamsDefaultValue,
            'spid' => $numericParamsDefaultValue,
            'threadTitle' => $textMatchParamsDefaultValue,
            'postContent' => $textMatchParamsDefaultValue,
            'threadViewNum' => $numericParamsDefaultValue,
            'threadShareNum' => $numericParamsDefaultValue,
            'threadReplyNum' => $numericParamsDefaultValue,
            'replySubReplyNum' => $numericParamsDefaultValue,
            'authorUid' => $numericParamsDefaultValue,
            'authorName' => $textMatchParamsDefaultValue,
            'authorDisplayName' => $textMatchParamsDefaultValue,
            'authorExpGrade' => $numericParamsDefaultValue,
            'latestReplierUid' => $numericParamsDefaultValue,
            'latestReplierName' => $textMatchParamsDefaultValue,
            'latestReplierDisplayName' => $textMatchParamsDefaultValue,
        ];
        foreach ($queryParams as $paramIndex => $param) { // set sub params with default value
            foreach ($subParamsDefaultValue[array_keys($param)[0]] ?? [] as $subParamName => $subParamDefaultValue) {
                $queryParams[$paramIndex][$subParamName] ??= $subParamDefaultValue;
                $queryParams[$paramIndex]['not'] ??= false;
            }
        }

        $paramsRequiredPostTypes = [
            'pid' => [['reply', 'subReply'], 'OR'],
            'spid' => [['subReply'], 'AND'],
            'latestReplyTime' => [['thread'], 'AND'],
            'threadTitle' => [['thread'], 'AND'],
            'postContent' => [['reply', 'subReply'], 'OR'],
            'threadViewNum' => [['thread'], 'AND'],
            'threadShareNum' => [['thread'], 'AND'],
            'threadReplyNum' => [['thread'], 'AND'],
            'replySubReplyNum' => [['reply'], 'AND'],
            'threadProperties' => [['thread'], 'AND'],
            'authorExpGrade' => [['reply', 'subReply'], 'OR'],
            'latestReplierUid' => [['thread'], 'AND'],
            'latestReplierName' => [['thread'], 'AND'],
            'latestReplierDisplayName' => [['thread'], 'AND'],
            'latestReplierGender' => [['thread'], 'AND']
        ];
        foreach ($paramsRequiredPostTypes as $paramName => $requiredPostTypes) {
            if ($filterParams($paramName) !== []) {
                Helper::abortAPIIfNot(40003, $requiredPostTypes[1] === 'OR'
                    ? array_diff($getParamValue('postTypes'), $requiredPostTypes[0]) === []
                    : $getParamValue('postTypes') === array_sort($requiredPostTypes[0]));
            }
        }

        $orderByRequiredPostTypes = [
            'pid' => [['reply', 'subReply'], 'OR'],
            'spid' => [['subReply'], 'OR']
        ];
        if (\array_key_exists($getParamValue('orderBy'), $orderByRequiredPostTypes)) {
            $currentOrderByRequiredPostTypes = $orderByRequiredPostTypes[$getParamValue('orderBy')];
            Helper::abortAPIIfNot(40004, $currentOrderByRequiredPostTypes[1] === 'OR'
                ? array_diff($getParamValue('postTypes'), $currentOrderByRequiredPostTypes[0]) === []
                : $getParamValue('postTypes') === array_sort($currentOrderByRequiredPostTypes[0]));
        }

        if ($isSearchQuery) {
            $queryResult = $this->searchQuery($queryParams);
        } elseif ($isIndexQuery) {
            $queryResult = $this->indexQuery(array_reduce($filterParams([...$uniqueParamsName, ...$postIDParamsName]), fn(array $flatParams, array $param): array => array_merge($flatParams, $param), [])); // flatten unique query params
        }
        ['result' => $queryResult, 'pages' => $pagesInfo] = $queryResult;

        return [
            'pages' => $pagesInfo,
            'forum' => ForumModel::where('fid', $queryResult['fid'])->hidePrivateFields()->first()->toArray(),
            'threads' => $this->getNestedPostsInfoByID($queryResult, $isIndexQuery),
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

    /**
     * Apply search query param's condition on posts model's query builder
     *
     * @param Builder $postQuery
     * @param array $param
     * @return Builder
     */
    private function applySearchQueryOnPostModel(Builder $postQuery, array $param): Builder
    {
        $applyTextMatchParamsQuery = function (Builder $query, string $fieldName, string $notString, $paramValue, array $param): Builder {
            if ($param['matchBy'] === 'regex') {
                return $query->where($fieldName, "{$notString} REGEXP", $paramValue);
            }
            return $query->where(function ($query) use ($param, $fieldName, $notString, $paramValue) {
                $notOrWhere = $notString === 'Not' ? '' : 'or'; // not (A or B) <=> not A and not B, following https://en.wikipedia.org/wiki/De_Morgan%27s_laws
                $addMatchKeyword = fn(string $keyword) => $query->{"{$notOrWhere}Where"}($fieldName, "{$notString} LIKE", $param['matchBy'] === 'implicit' ? "%{$keyword}%" : $keyword);
                if ($param['spaceSplit']) {
                    foreach (explode(' ', $paramValue) as $splitedKeyword) { // split multiple search keyword by space char
                        $addMatchKeyword($splitedKeyword);
                    }
                } else {
                    $addMatchKeyword($paramValue);
                }
            });
        };
        $paramName = array_keys($param)[0];
        $paramValue = $param[$paramName];

        $numericParamsReverseRange = [
            '<' => '>=',
            '=' => '!=',
            '>' => '<='
        ];
        $not = $param['not'] ?? false ? 'Not' : ''; // unique params doesn't have not sub param
        $reverseNot = $param['not'] ?? false ? '' : 'Not';
        switch ($paramName) {
            // uniqueParams
            case 'orderBy':
                if ($paramValue === 'default') {
                    return $postQuery->orderByDesc('postTime');
                }
                return $postQuery->orderBy($paramValue, $param['direction']);
                break;
            // numericParams
            case 'tid':
            case 'pid':
            case 'spid':
            case 'authorUid':
            case 'authorExpGrade':
            case 'latestReplierUid':
            case 'threadViewNum':
            case 'threadShareNum':
            case 'threadReplyNum':
            case 'replySubReplyNum':
                $fieldsName = [
                    'threadViewNum' => 'viewNum',
                    'threadShareNum' => 'shareNum',
                    'threadReplyNum' => 'replyNum',
                    'replySubReplyNum' => 'subReplyNum'
                ];
                if ($param['range'] === 'IN' || $param['range'] === 'BETWEEN') {
                    $cause = $not . $param['range'];
                    return $postQuery->{"where{$cause}"}($fieldsName[$paramName] ?? $paramName, explode(',', $paramValue));
                }
                return $postQuery->where($fieldsName[$paramName] ?? $paramName, $param['not'] ? $numericParamsReverseRange[$param['range']] : $param['range'], $paramValue);
                break;
            // textMatchParams
            case 'threadTitle':
            case 'postContent':
                return $applyTextMatchParamsQuery($postQuery, $paramName === 'threadTitle' ? 'title' : 'content', $not, $paramValue, $param);
                break;

            case 'postTime':
            case 'latestReplyTime':
                return $postQuery->{"where{$not}Between"}($paramName, explode(',', $paramValue));
                break;

            case 'threadProperties':
                foreach ($paramValue as $threadProperty) {
                    switch ($threadProperty) {
                        case 'good':
                            $postQuery = $postQuery->where('isGood', $param['not'] ? false : true);
                            break;
                        case 'sticky':
                            $postQuery = $postQuery->{"where{$reverseNot}Null"}('stickyType');
                            break;
                    }
                }
                return $postQuery;
                break;

            case 'authorName':
            case 'latestReplierName':
            case 'authorDisplayName':
            case 'latestReplierDisplayName':
                $userType = str_starts_with($paramName, 'author') ? 'author' : 'latestReplier';
                $fieldName = str_ends_with($paramName, 'DisplayName') ? 'displayName' : 'name';
                return $postQuery->{"where{$not}In"}("{$userType}Uid", $applyTextMatchParamsQuery(UserModel::newQuery(), $fieldName, $not, $paramValue, $param));
                break;
            case 'authorGender':
            case 'latestReplierGender':
                $userType = str_starts_with($paramName, 'author') ? 'author' : 'latestReplier';
                return $postQuery->{"where{$not}In"}("{$userType}Uid", UserModel::where('gender', $paramValue));
                break;
            case 'authorManagerType':
                if ($paramValue === 'NULL') {
                    return $postQuery->{"where{$not}Null"}('authorManagerType');
                }
                return $postQuery->where('authorManagerType', $param['not'] ? '!=' : '=', $paramValue);
                break;
            default:
                return $postQuery;
        }
    }

    private function searchQuery(array $queryParams): array
    {
        $getUniqueParamValue = fn(string $name) => array_first($queryParams, fn(array $param): bool => array_keys($param)[0] === $name)[$name];
        $postQueries = [];
        foreach (Arr::only(PostModelFactory::getPostModelsByFid($getUniqueParamValue('fid')), $getUniqueParamValue('postTypes')) as $postType => $postModel) {
            $postQuery = $postModel->newQuery();
            foreach ($queryParams as $param) {
                $postQueries[$postType] = $this->applySearchQueryOnPostModel($postQuery, $param);
            }
        }

        $postsQueriedInfo = [
            'fid' => $getUniqueParamValue('fid'),
            'thread' => [],
            'reply' => [],
            'subReply' => []
        ];
        foreach ($postQueries as $postType => $postQuery) {
            $postQueries[$postType] = $postQuery->hidePrivateFields()->simplePaginate($this->pagingPerPageItems);
            $postsQueriedInfo[$postType] = $postQueries[$postType]->toArray()['data'];
        }
        Helper::abortAPIIf(40401, array_keys(array_filter($postsQueriedInfo)) === ['fid']);

        /**
         * Union builders pagination $unionMethodName data by $unionStatement
         *
         * @param array $queryBuilders
         * @param string $unionMethodName
         * @param callable $unionStatement
         * @return mixed $unionStatement()
         */
        $pageInfoUnion = function (array $queryBuilders, string $unionMethodName, callable $unionStatement) {
            $unionValues = [];
            foreach ($queryBuilders as $queryBuilder) {
                $unionValues[] = $queryBuilder->$unionMethodName();
            }
            $unionValues = array_filter($unionValues); // array_filter() will remove falsy values
            return $unionStatement($unionValues === [] ? [0] : $unionValues); // prevent empty array
        };

        return [
            'result' => $postsQueriedInfo,
            'pages' => [ // todo: should cast simplePagination to array in $postQueries to prevent dynamic call method by string
                'firstItem' => $pageInfoUnion($postQueries, 'firstItem', fn(array $unionValues) => min($unionValues)),
                'itemsCount' => $pageInfoUnion($postQueries, 'count', fn(array $unionValues) => array_sum($unionValues)),
                'currentPage' => $pageInfoUnion($postQueries, 'currentPage', fn(array $unionValues) => min($unionValues))
            ]
        ];
    }

    private function indexQuery(array $flatQueryParams): array
    {
        $indexQuery = IndexModel::where(Arr::only($flatQueryParams, ['fid', 'tid', 'pid', 'spid']));
        if ($flatQueryParams['orderBy'] !== 'default') {
            $indexQuery->orderBy($flatQueryParams['orderBy'], $flatQueryParams['direction']);
        } elseif (Arr::only($flatQueryParams, ['tid', 'pid', 'spid']) === []) { // query by fid only
            $indexQuery->orderByDesc('postTime'); // order by postTime to prevent posts out of order when order by post id
        } else { // query by post id
            $indexQuery = $indexQuery->orderByMulti([ // order by all posts id to keep reply and sub reply continuous instated of clip into multi page since they are vary in postTime
                'tid' => 'ASC',
                'pid' => 'ASC',
                'spid' => 'ASC'
            ]);
        }

        $indexQuery = $indexQuery->whereIn('type', $flatQueryParams['postTypes'])->simplePaginate($this->pagingPerPageItems);
        Helper::abortAPIIf(40401, $indexQuery->isEmpty());

        $postsQueriedInfo = [
            'fid' => 0,
            'thread' => [],
            'reply' => [],
            'subReply' => []
        ];
        $postsQueriedInfo['fid'] = $indexQuery->pluck('fid')->first();
        foreach (['thread' => 'tid', 'reply' => 'pid', 'subReply' => 'spid'] as $postType => $postIDName) { // assign queried posts id from $indexQuery
            $postsQueriedInfo[$postType] = $indexQuery->where('type', $postType)->toArray();
        }

        return [
            'result' => $postsQueriedInfo,
            'pages' => [
                'firstItem' => $indexQuery->firstItem(),
                'itemsCount' => $indexQuery->count(),
                'currentPage' => $indexQuery->currentPage()
            ]
        ];
    }

    private function getNestedPostsInfoByID(array $postsInfo, bool $isInfoOnlyContainsPostsID): array
    {
        $postModels = PostModelFactory::getPostModelsByFid($postsInfo['fid']);
        $tids = array_column($postsInfo['thread'], 'tid');
        $pids = array_column($postsInfo['reply'], 'pid');
        $spids = array_column($postsInfo['subReply'], 'spid');
        $threadsInfo = collect($tids === []
            ? []
            : ($isInfoOnlyContainsPostsID
                ? $postModels['thread']->tid($tids)->hidePrivateFields()->get()->toArray()
                : $postsInfo['thread']
            ));
        $repliesInfo = collect($pids === []
            ? []
            : ($isInfoOnlyContainsPostsID
                ? $postModels['reply']->pid($pids)->hidePrivateFields()->get()->toArray()
                : $postsInfo['reply']
            ));
        $subRepliesInfo = collect($spids === []
            ? []
            : ($isInfoOnlyContainsPostsID
                ? $postModels['subReply']->spid($spids)->hidePrivateFields()->get()->toArray()
                : $postsInfo['subReply']
            ));

        $isSubIDsMissInOriginIDs = fn(Collection $originIDs, Collection $subIDs): bool
        => $subIDs->contains(
            fn(int $subID): bool => ! $originIDs->contains($subID)
        );

        $tidsInReplies = $repliesInfo->pluck('tid')->concat($subRepliesInfo->pluck('tid'))->unique()->sort()->values();
        // $tids must be first argument to ensure the diffed $tidsInReplies existing
        if ($isSubIDsMissInOriginIDs(collect($tids), $tidsInReplies)) {
            // fetch complete threads info which appeared in replies and sub replies info but missed in $tids
            $threadsInfo = collect($postModels['thread']
                ->tid($tidsInReplies->concat($tids)->toArray())
                ->hidePrivateFields()->get()->toArray());
        }

        $pidsInThreadsAndSubReplies = $subRepliesInfo->pluck('pid');
        if ($pids === []) { // append thread's first reply when there's no pid
            $pidsInThreadsAndSubReplies = $pidsInThreadsAndSubReplies->concat($threadsInfo->pluck('firstPid'));
        }
        $pidsInThreadsAndSubReplies = $pidsInThreadsAndSubReplies->unique()->sort()->values();
        // $pids must be first argument to ensure the diffed $pidsInSubReplies existing
        if ($isSubIDsMissInOriginIDs(collect($pids), $pidsInThreadsAndSubReplies)) {
            // fetch complete replies info which appeared in threads and sub replies info but missed in $pids
            $repliesInfo = collect($postModels['reply']
                ->pid($pidsInThreadsAndSubReplies->concat($pids)->toArray())
                ->hidePrivateFields()->get()->toArray());
        }

        $convertJsonContentToHtml = function (array $post): array {
            if ($post['content'] !== null) {
                $post['content'] = Post::convertJsonContentToHtml($post['content']);
            }
            return $post;
        };
        $repliesInfo->transform($convertJsonContentToHtml);
        $subRepliesInfo->transform($convertJsonContentToHtml);

        return static::convertNestedPostsInfo($threadsInfo->toArray(), $repliesInfo->toArray(), $subRepliesInfo->toArray());
    }

    private static function convertNestedPostsInfo(array $threadsInfo = [], array $repliesInfo = [], array $subRepliesInfo = []): array
    {
        $threadsInfo = Helper::setKeyWithItemsValue($threadsInfo, 'tid');
        $repliesInfo = Helper::setKeyWithItemsValue($repliesInfo, 'pid');
        $subRepliesInfo = Helper::setKeyWithItemsValue($subRepliesInfo, 'spid');
        $nestedPostsInfo = [];

        foreach ($threadsInfo as $tid => $thread) {
            $threadReplies = collect($repliesInfo)->where('tid', $tid)->toArray(); // can't use values() here to prevent losing posts id key
            foreach ($threadReplies as $pid => $reply) {
                // values() and array_values() remove keys to simplify json data
                $threadReplies[$pid]['subReplies'] = collect($subRepliesInfo)->where('pid', $pid)->values()->toArray();
            }
            $nestedPostsInfo[$tid] = array_merge($thread, ['replies' => array_values($threadReplies)]);
        }

        return array_values($nestedPostsInfo);
    }
}
