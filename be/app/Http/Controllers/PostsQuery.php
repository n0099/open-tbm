<?php

namespace App\Http\Controllers;

use App\Helper;
use App\Tieba\Post\Post;
use App\Tieba\Eloquent\IndexModel;
use App\Tieba\Eloquent\ForumModel;
use App\Tieba\Eloquent\UserModel;
use App\Tieba\Eloquent\PostModelFactory;
use GuzzleHttp\Utils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class PostsQuery extends Controller
{
    private int $pagingPerPageItems = 200;

    private static function getParamName(array $param): string
    {
        return (string)array_keys($param)[0];
    }

    public function query(\Illuminate\Http\Request $request): array
    {
        $filterParams = function (array | string $names) use (&$queryParams): array {
            // cannot use one-liner fn () => syntax here since we need $queryParams to sync changes
            // array_values() will remove keys remained by array_filter() from original $queryParams
            return array_values(array_filter(
                $queryParams,
                fn (array $param): bool => \in_array(self::getParamName($param), (array)$names, true)
            ));
        };
        // only get first occurred param's value
        $getParamValue = fn (string $name) => $filterParams($name)[0][$name] ?? null;
        $setParamValue = function (string $name, $value) use (&$queryParams): void {
            $filteredParams = array_keys(array_filter($queryParams, fn (array $param): bool => self::getParamName($param) === $name));
            if ($filteredParams === []) {
                throw new \InvalidArgumentException('Cannot find param with given param name');
            }
            $queryParams[$filteredParams[0]][$name] = $value; // only set first param's value which occurs in $queryParams
        };

        $queryParams = (array)Utils::jsonDecode($request->validate([
            'page' => 'integer',
            'query' => 'json'
        ])['query'], true);
        $paramsValidValue = [
            'userGender' => [0, 1, 2],
            'userManagerType' => ['NULL', 'manager', 'assist', 'voiceadmin']
        ];
        $dateRangeValidator = function ($_, string $value): void {
            \Validator::make(
                explode(',', $value),
                ['0' => 'date|before_or_equal:1', '1' => 'date|after_or_equal:0']
            )->validate();
        };
        // note here we haven't validate that is every sub param have a corresponding main param yet
        \Validator::make($queryParams, [
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
            '*.range' => 'in:<,=,>,IN,BETWEEN',
            // sub param of threadTitle, postContent, authorName, authorDisplayName, latestReplierName, latestReplierDisplayName
            '*.matchBy' => 'in:implicit,explicit,regex',
            '*.spaceSplit' => 'boolean'
        ])->validate();
        // only fill postTypes and/or orderBy uniqueParam doesn't query anything
        Helper::abortAPIIf(40001, \count($queryParams) === \count($filterParams(['postTypes', 'orderBy'])));

        $uniqueParamsName = ['fid', 'postTypes', 'orderBy'];
        $isPostIDQuery = \count($queryParams) === \count($filterParams([...$uniqueParamsName, ...Helper::POSTS_ID])) // is there no other params
            && \count($filterParams(Helper::POSTS_ID)) === 1 // is there only one post id param
            && array_column($queryParams, 'range') === []; // is post id param haven't any sub param
        // is fid param exists and there's no other params
        $isFidParamNull = $getParamValue('fid') === null;
        $isFidQuery = !$isFidParamNull && \count($queryParams) === \count($filterParams($uniqueParamsName));
        $isIndexQuery = $isPostIDQuery || $isFidQuery;
        $isSearchQuery = !$isIndexQuery;
        if ($isSearchQuery) {
            Helper::abortAPIIf(40002, $isFidParamNull);
        }

        foreach ($uniqueParamsName as $uniqueParamName) { // is all unique param only appeared once
            Helper::abortAPIIf(40005, \count($filterParams($uniqueParamName)) > 1);
        }
        $uniqueParamsDefaultValue = [
            'postTypes' => ['value' => Helper::POST_TYPES],
            'orderBy' => ['value' => 'default', 'subParam' => ['direction' => 'default']]
        ];
        foreach ($uniqueParamsDefaultValue as $uniqueParamName => $uniqueParamDefaultValue) {
            // add unique params with default value when it's not presented in request
            if ($getParamValue($uniqueParamName) === null) {
                $queryParams[] = array_merge(
                    [$uniqueParamName => $uniqueParamDefaultValue['value']],
                    $uniqueParamDefaultValue['subParam'] ?? []
                );
            }
        }

        $paramDefaultValueByType = [
            'numeric' => ['range' => '='],
            'text' => ['matchBy' => 'implicit', 'spaceSplit' => false]
        ];
        $paramsNameByType = [
            'numeric' => [
                'tid',
                'pid',
                'spid',
                'threadViewNum',
                'threadShareNum',
                'threadReplyNum',
                'replySubReplyNum',
                'authorUid',
                'authorExpGrade',
                'latestReplierUid'
            ],
            'text' => [
                'threadTitle',
                'postContent',
                'authorName',
                'authorDisplayName',
                'latestReplierName',
                'latestReplierDisplayName'
            ]
        ];
        $subParamsDefaultValue = collect($paramsNameByType)->flatMap(fn (array $names, string $type) =>
            array_fill_keys($names, $paramDefaultValueByType[$type]))->toArray();
        foreach ($queryParams as &$param) { // set sub params with default value
            foreach ($subParamsDefaultValue[self::getParamName($param)] ?? [] as $subParamName => $subParamDefaultValue) {
                $param[$subParamName] ??= $subParamDefaultValue;
            }
        }
        unset($param);

        $setParamValue('postTypes', Arr::sort($getParamValue('postTypes'))); // sort here to prevent further sort while validating
        $currentPostTypes = $getParamValue('postTypes');
        $isRequiredPostTypes = fn (array $current, array $required): bool =>
            $required[0] === 'SUB'
                ? array_diff($current, Arr::sort($required[1])) === []
                : $current === Arr::sort($required[1]);

        $paramsRequiredPostTypes = [
            'pid' => ['SUB', ['reply', 'subReply']],
            'spid' => ['ALL', ['subReply']],
            'latestReplyTime' => ['ALL', ['thread']],
            'threadTitle' => ['ALL', ['thread']],
            'postContent' => ['SUB', ['reply', 'subReply']],
            'threadViewNum' => ['ALL', ['thread']],
            'threadShareNum' => ['ALL', ['thread']],
            'threadReplyNum' => ['ALL', ['thread']],
            'replySubReplyNum' => ['ALL', ['reply']],
            'threadProperties' => ['ALL', ['thread']],
            'authorExpGrade' => ['SUB', ['reply', 'subReply']],
            'latestReplierUid' => ['ALL', ['thread']],
            'latestReplierName' => ['ALL', ['thread']],
            'latestReplierDisplayName' => ['ALL', ['thread']],
            'latestReplierGender' => ['ALL', ['thread']]
        ];
        foreach ($paramsRequiredPostTypes as $paramName => $requiredPostTypes) {
            if ($filterParams($paramName) !== []) {
                Helper::abortAPIIfNot(40003, !$isRequiredPostTypes($currentPostTypes, $requiredPostTypes));
            }
        }

        $orderByRequiredPostTypes = [
            'pid' => ['SUB', ['reply', 'subReply']],
            'spid' => ['SUB', ['subReply']]
        ];
        $currentOrderBy = $getParamValue('orderBy');
        if (\array_key_exists($currentOrderBy, $orderByRequiredPostTypes)) {
            Helper::abortAPIIfNot(40004, !$isRequiredPostTypes($currentPostTypes, $orderByRequiredPostTypes[$currentOrderBy]));
        }

        if ($isSearchQuery) {
            $queryResult = $this->searchQuery($queryParams);
        } elseif ($isIndexQuery) {
            $queryResult = $this->indexQuery(array_reduce(
                $filterParams([...$uniqueParamsName, ...Helper::POSTS_ID]),
                fn (array $flatParams, array $param): array => array_merge($flatParams, $param),
                []
            )); // flatten unique query params
        }
        ['result' => $queryResult, 'pages' => $pagesInfo] = $queryResult;

        return [
            'pages' => $pagesInfo,
            'forum' => ForumModel::where('fid', $queryResult['fid'])->hidePrivateFields()->first()?->toArray(),
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
                $addMatchKeyword = fn (string $keyword) => $query->{"{$notOrWhere}Where"}($fieldName, "{$notString} LIKE", $param['matchBy'] === 'implicit' ? "%{$keyword}%" : $keyword);
                if ($param['spaceSplit']) {
                    foreach (explode(' ', $paramValue) as $splitedKeyword) { // split multiple search keyword by space char
                        $addMatchKeyword($splitedKeyword);
                    }
                } else {
                    $addMatchKeyword($paramValue);
                }
            });
        };
        $paramName = self::getParamName($param);
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
            // textMatchParams
            case 'threadTitle':
            case 'postContent':
                return $applyTextMatchParamsQuery($postQuery, $paramName === 'threadTitle' ? 'title' : 'content', $not, $paramValue, $param);

            case 'postTime':
            case 'latestReplyTime':
                return $postQuery->{"where{$not}Between"}($paramName, explode(',', $paramValue));

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

            case 'authorName':
            case 'latestReplierName':
            case 'authorDisplayName':
            case 'latestReplierDisplayName':
                $userType = str_starts_with($paramName, 'author') ? 'author' : 'latestReplier';
                $fieldName = str_ends_with($paramName, 'DisplayName') ? 'displayName' : 'name';
                return $postQuery->{"where{$not}In"}("{$userType}Uid", $applyTextMatchParamsQuery(UserModel::newQuery(), $fieldName, $not, $paramValue, $param));
            case 'authorGender':
            case 'latestReplierGender':
                $userType = str_starts_with($paramName, 'author') ? 'author' : 'latestReplier';
                return $postQuery->{"where{$not}In"}("{$userType}Uid", UserModel::where('gender', $paramValue));
            case 'authorManagerType':
                if ($paramValue === 'NULL') {
                    return $postQuery->{"where{$not}Null"}('authorManagerType');
                }
                return $postQuery->where('authorManagerType', $param['not'] ? '!=' : '=', $paramValue);
            default:
                return $postQuery;
        }
    }

    private function searchQuery(array $queryParams): array
    {
        $getUniqueParamValue = fn (string $name) =>
            Arr::first($queryParams, fn (array $param): bool => self::getParamName($param) === $name)[$name];
        $postQueries = [];
        foreach (Arr::only(
            PostModelFactory::getPostModelsByFid($getUniqueParamValue('fid')),
            $getUniqueParamValue('postTypes')
        ) as $postType => $postModel) {
            $postQuery = $postModel->newQuery();
            foreach ($queryParams as $param) {
                $postQueries[$postType] = $this->applySearchQueryOnPostModel($postQuery, $param);
            }
        }

        $postsQueriedInfo = array_merge(
            ['fid' => $getUniqueParamValue('fid')],
            array_fill_keys(Helper::POST_TYPES, [])
        );
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
                'firstItem' => $pageInfoUnion($postQueries, 'firstItem', fn (array $unionValues) => min($unionValues)),
                'itemsCount' => $pageInfoUnion($postQueries, 'count', fn (array $unionValues) => array_sum($unionValues)),
                'currentPage' => $pageInfoUnion($postQueries, 'currentPage', fn (array $unionValues) => min($unionValues))
            ]
        ];
    }

    private function indexQuery(array $flatQueryParams): array
    {
        $indexQuery = IndexModel::where(Arr::only($flatQueryParams, ['fid', ...Helper::POSTS_ID]));
        if ($flatQueryParams['orderBy'] !== 'default') {
            $indexQuery->orderBy($flatQueryParams['orderBy'], $flatQueryParams['direction']);
        } elseif (Arr::only($flatQueryParams, Helper::POSTS_ID) === []) { // query by fid only
            $indexQuery->orderByDesc('postTime'); // order by postTime to prevent posts out of order when order by post id
        } else { // query by post id
            // order by all posts id to keep reply and sub reply continuous instated of clip into multi page since they are vary in postTime
            $indexQuery = $indexQuery->orderByMulti(array_fill_keys(Helper::POSTS_ID, 'ASC'));
        }

        $indexQuery = $indexQuery->whereIn('type', $flatQueryParams['postTypes'])->simplePaginate($this->pagingPerPageItems);
        Helper::abortAPIIf(40401, $indexQuery->isEmpty());

        $postsQueriedInfo = array_merge(
            ['fid' => $indexQuery->pluck('fid')->first()],
            array_map( // assign queried posts id from $indexQuery
                fn ($postType) => $indexQuery->where('type', $postType)->toArray(),
                array_combine(Helper::POST_TYPES, Helper::POSTS_ID)
            )
        );

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
            : (
                $isInfoOnlyContainsPostsID
                ? $postModels['thread']->tid($tids)->hidePrivateFields()->get()->toArray()
                : $postsInfo['thread']
            ));
        $repliesInfo = collect($pids === []
            ? []
            : (
                $isInfoOnlyContainsPostsID
                ? $postModels['reply']->pid($pids)->hidePrivateFields()->get()->toArray()
                : $postsInfo['reply']
            ));
        $subRepliesInfo = collect($spids === []
            ? []
            : (
                $isInfoOnlyContainsPostsID
                ? $postModels['subReply']->spid($spids)->hidePrivateFields()->get()->toArray()
                : $postsInfo['subReply']
            ));

        $isSubIDsMissInOriginIDs = fn (Collection $originIDs, Collection $subIDs): bool
        => $subIDs->contains(
            fn (int $subID): bool => !$originIDs->contains($subID)
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
