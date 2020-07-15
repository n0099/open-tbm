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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostsQuery extends Controller
{
    private int $pagingPerPageItems = 200;

    public function query(\Illuminate\Http\Request $request)
    {
        $paramsValidValue = [
            'orderDirection' => ['ASC', 'DESC'],
            'range' => ['<', '=', '>'],
            'userGender' => [0, 1, 2],
            'userManagerType' => ['NULL', 'manager', 'assist', 'voiceadmin']
        ];
        $customQueryParamsValidate = [
            'tidRange' => Rule::in($paramsValidValue['range']),
            'pidRange' => Rule::in($paramsValidValue['range']),
            'spidRange' => Rule::in($paramsValidValue['range']),
            'threadTitle' => 'string|required_with:threadTitleRegex',
            'threadTitleRegex' => 'boolean',
            'postContent' => 'string|required_with:postContentRegex',
            'postContentRegex' => 'boolean',
            'postTimeStart' => 'date|required_with:postTimeEnd|before:postTimeEnd',
            'postTimeEnd' => 'date|required_with:postTimeStart|after:postTimeStart',
            'latestReplyTimeStart' => 'date|required_with:latestReplyTimeEnd|before:latestReplyTimeEnd',
            'latestReplyTimeEnd' => 'date|required_with:latestReplyTimeStart|after:latestReplyTimeStart',
            'threadProperty' => 'array', // todo: rename to threadProperties
            'threadReplyNum' => 'integer|required_with:threadReplyNumRange',
            'threadReplyNumRange' => Rule::in($paramsValidValue['range']),
            'replySubReplyNum' => 'integer|required_with:replySubReplyNumRange',
            'replySubReplyNumRange' => Rule::in($paramsValidValue['range']),
            'threadViewNum' => 'integer|required_with:threadViewNumRange',
            'threadViewNumRange' => Rule::in($paramsValidValue['range']),
            'threadShareNum' => 'integer|required_with:threadShareNumRange',
            'threadShareNumRange' => Rule::in($paramsValidValue['range']),
            'userType' => 'array', // todo: rename to userTypes
            'userID' => 'int',
            'userName' => 'string',
            'userDisplayName' => 'string',
            'userExpGrade' => 'integer|required_with:userExpGradeRange',
            'userExpGradeRange' => Rule::in($paramsValidValue['range']),
            'userGender' => Rule::in($paramsValidValue['userGender']),
            'userManagerType' => Rule::in($paramsValidValue['userManagerType']),
        ];
        $paramsDefaultValue = [
            'postType' => ['thread', 'reply', 'subReply'], // todo: rename to postTypes
            'userType' => ['author'],
            // belows are custom query params and they are belongs to another param
            'tidRange' => '=',
            'pidRange' => '=',
            'spidRange' => '=',
            'threadTitleRegex' => false,
            'postContentRegex' => false,
            'threadReplyNumRange' => '=',
            'replySubReplyNumRange' => '=',
            'threadViewNumRange' => '=',
            'threadShareNumRange' => '=',
            'userExpGradeRange' => '=',
        ];

        $requestQueryParams = $request->validate(array_merge([
            'page' => 'integer',
            'fid' => 'integer',
            'tid' => 'integer|required_with:tidRange,userExpGradeRange',
            'pid' => 'integer|required_with:pidRange',
            'spid' => 'integer|required_with:spidRange',
            'postType' => 'array',
            'orderBy' => 'string',
            'orderDirection' => Rule::in($paramsValidValue['orderDirection']),
        ], $customQueryParamsValidate));
        $queryParams = collect(array_merge($paramsDefaultValue, $requestQueryParams)); // fill with default params value

        $queryForumAndPostsID = $queryParams->only(['fid', 'tid', 'pid', 'spid'])->filter(); // filter() will remove falsy values
        $isIndexQuery = $queryForumAndPostsID->isNotEmpty();
        $isCustomQuery = array_intersect_key($requestQueryParams, $customQueryParamsValidate) !== [];

        $queryParams['postType'] = array_sort($queryParams['postType']); // sort here to prevent further sort while validating
        $queryParams['orderDirection'] ??= $isIndexQuery ? 'ASC' : 'DESC';
        $queryParams['orderBy'] ??= 'postTime'; // default values
        $orderByRequiredPostType = [
            'tid' => ['thread', 'reply', 'subReply'],
            'pid' => ['reply', 'subReply'],
            'spid' => ['subReply']
        ];
        Helper::abortAPIIf(40006, Str::contains($queryParams['orderBy'], array_keys($orderByRequiredPostType))
            && array_sort($orderByRequiredPostType[$queryParams['orderBy']]) === $queryParams['postType']);

        if ($isCustomQuery) {
            // custom query params relation validate
            if (! $queryParams->has('fid')) {
                $queryPostsID = $queryForumAndPostsID->except('fid');
                Helper::abortAPIIf(40002, $queryPostsID->isEmpty());
                $queryParams['fid'] = IndexModel::where($queryPostsID)->firstOrFail(['fid'])->toArray()['fid'];
            }
            $customQueryParamsRequiredPostTypes = [
                'pid' => ['reply', 'subReply'],
                'spid' => ['subReply'],
                'threadTitle' => ['thread'],
                'latestReplyTimeStart' => ['thread'],
                'threadProperty' => ['thread'],
                'threadReplyNum' => ['thread'],
                'replySubReplyNum' => ['reply'],
                'threadViewNum' => ['thread'],
                'threadShareNum' => ['thread'],
                'postContent' => ['reply', 'subReply']
            ];
            foreach ($customQueryParamsRequiredPostTypes as $paramName => $requiredPostTypes) {
                if ($queryParams->has($paramName)) {
                    Helper::abortAPIIf(40005, array_sort($requiredPostTypes) !== $queryParams['postType']);
                }
            }
            if (in_array('latestReplier', $queryParams['userType'], true)) {
                Helper::abortAPIIf(40003, ! in_array('thread', $queryParams['postType'], true));
                Helper::abortAPIIf(40004, $queryParams->only([
                    'userExpGrade',
                    'userExpGradeRange',
                    'userManagerType'
                ])->isNotEmpty());
            }

            $queryResult = $this->customQuery($queryParams);
        } elseif ($isIndexQuery) {
            $queryResult = $this->indexQuery($queryParams, $queryForumAndPostsID);
        } else {
            Helper::abortAPI(40001);
        }
        ['result' => $queryResult, 'pages' => $pagesInfo] = $queryResult;

        return [
            'pages' => $pagesInfo,
            'forum' => ForumModel::where('fid', $queryResult['fid'])->hidePrivateFields()->first()->toArray(),
            'threads' => $this->getNestedPostsInfoByID($queryResult, $isIndexQuery, $queryParams['orderBy'], $queryParams['orderDirection']),
            'users' => UserModel::whereIn(
                'uid',
                collect([
                    Arr::pluck($queryResult['thread'], 'authorUid'),
                    Arr::pluck($queryResult['thread'], 'latestReplierUid'),
                    Arr::pluck($queryResult['reply'], 'authorUid'),
                    Arr::pluck($queryResult['subReply'], 'authorUid')
                ])->flatten()->unique()->sort()->values()->toArray()
            )->hidePrivateFields()->get()->toArray()
        ];
    }

    /**
     * Apply custom query param's condition on posts model
     *
     * @param string $postType
     * @param Builder $postQuery
     * @param string $paramName
     * @param mixed $paramValue
     * @param Collection $otherQueryParams
     * @return Builder
     */
    private function applyCustomQueryOnPostModel(string $postType, Builder $postQuery, string $paramName, $paramValue, Collection $otherQueryParams): Builder
    {
        $applyUserInfoSubQuery = function (Builder $postQuery, array $userTypes, string $queryUserBy, $paramValue): Builder {
            if ($queryUserBy === 'uid') {
                $uids = [$paramValue]; // query user by user defined id param directly
            } else {
                $uids = UserModel::where($queryUserBy, $paramValue);
            }
            foreach ($userTypes as $userType) {
                switch($userType) {
                    case 'author':
                        $postQuery = $postQuery->whereIn('authorUid', $uids);
                        break;
                    case 'latestReplier':
                        $postQuery = $postQuery->whereIn('latestReplierUid', $uids);
                        break;
                }
            }
            return $postQuery;
        };
        $applyDateTimeRangeParamOnQuery = fn(Builder $postQuery, string $fieldName, string $rangeStart, string $rangeEnd): Builder => $postQuery->whereBetween($fieldName, [$rangeStart, $rangeEnd]);

        switch ($paramName) {
            case 'tid':
                return $postQuery->where('tid', $otherQueryParams['tidRange'], $paramValue);
                break;
            case 'pid':
                return $postQuery->where('pid', $otherQueryParams['pidRange'], $paramValue);
                break;
            case 'spid':
                return $postQuery->where('spid', $otherQueryParams['spidRange'], $paramValue);
                break;
            case 'orderBy':
                return $postQuery->orderBy($otherQueryParams['orderBy'], $otherQueryParams['orderDirection']);
                break;
            case 'threadTitle':
                return $postQuery->where([
                    'title',
                    $otherQueryParams['threadTitleRegex'] ? 'REGEXP' : 'LIKE',
                    $otherQueryParams['threadTitleRegex'] ? $paramValue : "%{$paramValue}%",
                ]);
                break;
            case 'postContent':
                if ($otherQueryParams['postContentRegex']) {
                    return $postQuery->where('content', 'REGEXP', $paramValue);
                } else {
                    return $postQuery->where(function ($postModel) use ($paramValue) {
                        foreach (explode(' ', $paramValue) as $splitedParamValue) { // split param value by space char
                            $postModel = $postModel->where('content', 'LIKE', "%{$splitedParamValue}%");
                        }
                    });
                }
                break;
            case 'postTimeStart':
                return $applyDateTimeRangeParamOnQuery($postQuery, 'postTime', $paramValue, $otherQueryParams['postTimeEnd']);
                break;
            case 'latestReplyTimeStart':
                return $applyDateTimeRangeParamOnQuery($postQuery, 'latestReplyTime', $paramValue, $otherQueryParams['latestReplyTimeEnd']);
                break;
            case 'threadProperty':
                foreach ($paramValue as $threadProperty) {
                    switch ($threadProperty) {
                        case 'good':
                            $postQuery = $postQuery->where('isGood', true);
                            break;
                        case 'sticky':
                            $postQuery = $postQuery->whereNotNull('stickyType');
                            break;
                    }
                }
                return $postQuery;
                break;
            case 'threadReplyNum':
                return $postQuery->where('replyNum', $otherQueryParams['threadReplyNumRange'], $paramValue);
                break;
            case 'replySubReplyNum':
                return $postQuery->where('subReplyNum', $otherQueryParams['replySubReplyNumRange'], $paramValue);
                break;
            case 'threadViewNum':
                return $postQuery->where('viewNum', $otherQueryParams['threadViewNumRange'], $paramValue);
                break;
            case 'threadShareNum':
                return $postQuery->where('shareNum', $otherQueryParams['threadShareNumRange'], $paramValue);
                break;
            case 'userID':
                return $applyUserInfoSubQuery($postQuery, $otherQueryParams['userType'], 'uid', $paramValue);
                break;
            case 'userName':
                return $applyUserInfoSubQuery($postQuery, $otherQueryParams['userType'], 'name', $paramValue);
                break;
            case 'userDisplayName':
                return $applyUserInfoSubQuery($postQuery, $otherQueryParams['userType'], 'displayName', $paramValue);
                break;
            case 'userGender':
                return $applyUserInfoSubQuery($postQuery, $otherQueryParams['userType'], 'gender', $paramValue);
                break;
            case 'userExpGrade':
                if ($postType === 'thread') {
                    return $postQuery->whereIn('firstPid', PostModelFactory::newReply($otherQueryParams['fid'])->where([ // TODO: massive sub query
                        ['floor', '=', 1],
                        ['authorExpGrade', $otherQueryParams['userExpGradeRange'], $paramValue]
                    ]));
                } else {
                    return $postQuery->where('authorExpGrade', $otherQueryParams['userExpGradeRange'], $paramValue);
                }
                break;
            case 'userManagerType':
                if ($paramValue === 'NULL') {
                    return $postQuery->whereNull('authorManagerType');
                } else {
                    return $postQuery->where('authorManagerType', $paramValue);
                }
                break;
            default:
                return $postQuery;
        }
    }

    private function customQuery(Collection $queryParams): array
    {
        $postsModel = PostModelFactory::getPostModelsByFid($queryParams['fid']);
        $postQueries = [];
        /** @var array $queryPostsTypeModel post models within query posts type **/
        $queryPostsTypeModel = $queryParams['postType'] === [] ? $postsModel : Arr::only($postsModel, $queryParams['postType']);
        foreach ($queryPostsTypeModel as $postType => $postQuery) {
            $postQuery = $postQuery->newQuery();
            foreach ($queryParams as $paramName => $paramValue) {
                $postQueries[$postType] = $this->applyCustomQueryOnPostModel($postType, $postQuery, $paramName, $paramValue, $queryParams);
            }
        }

        $postsQueriedInfo = [
            'fid' => $queryParams['fid'],
            'thread' => [],
            'reply' => [],
            'subReply' => []
        ];
        foreach ($postQueries as $postType => $postQueryBuilder) {
            $postQueries[$postType] = $postQueryBuilder->hidePrivateFields()->simplePaginate($this->pagingPerPageItems);
            $postsQueriedInfo[$postType] = $postQueries[$postType]->toArray()['data'];
        }
        Helper::abortAPIIf(40401, array_keys(array_filter($postsQueriedInfo)) === ['fid']);

        /**
         * Union builders pagination $unionMethodName data by $unionStatement
         *
         * @param array $queryBuilders
         * @param string $unionMethodName
         * @param callable $unionStatement
         *
         * @return mixed $unionStatement
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
            'pages' => [
                'firstItem' => $pageInfoUnion($postQueries, 'firstItem', fn($unionValues) => min($unionValues)),
                'currentItems' => $pageInfoUnion($postQueries, 'count', fn($unionValues) => array_sum($unionValues)),
                'currentPage' => $pageInfoUnion($postQueries, 'currentPage', fn($unionValues) => min($unionValues))
            ]
        ];
    }

    private function indexQuery(Collection $queryParams, Collection $queryForumAndPostsID): array
    {
        $postsIDNamePair = [
            'thread' => 'tid',
            'reply' => 'pid',
            'subReply' => 'spid'
        ];
        $indexQuery = IndexModel::where($queryForumAndPostsID);

        if ($queryParams->has('orderBy')) {
            $indexQuery->orderBy($queryParams['orderBy'], $queryParams['orderDirection']);
        } elseif ($queryForumAndPostsID->keys() === ['fid']) { // query by fid only
            $indexQuery->orderBy('postTime', 'DESC'); // order by postTime to prevent posts out of order when order by post id
        } else { // query by post id
            $indexQuery = $indexQuery->orderByMulti([
                'tid' => 'ASC',
                'pid' => 'ASC',
                'spid' => 'ASC'
            ]);
        }

        $indexQuery = $indexQuery->whereIn('type', $queryParams['postType'])->simplePaginate($this->pagingPerPageItems);
        Helper::abortAPIIf(40401, $indexQuery->isEmpty());

        $postsQueriedInfo = [
            'fid' => 0,
            'thread' => [],
            'reply' => [],
            'subReply' => []
        ];
        $postsQueriedInfo['fid'] = $indexQuery->pluck('fid')->first();
        foreach ($postsIDNamePair as $postType => $postIDName) { // assign queried posts id from $indexQuery
            $postsQueriedInfo[$postType] = $indexQuery->where('type', $postType)->toArray();
        }
        return [
            'result' => $postsQueriedInfo,
            'pages' => [
                'firstItem' => $indexQuery->firstItem(),
                'currentItems' => $indexQuery->count(),
                'currentPage' => $indexQuery->currentPage()
            ]
        ];
    }

    private function getNestedPostsInfoByID(array $postsInfo, bool $isInfoOnlyContainsPostsID, ?string $orderBy, ?string $orderDirection): array
    {
        /** @var $postsInfo ['fid' => int, 'thread' => array, 'reply' => array, 'subReply' => array] */
        $postsModel = PostModelFactory::getPostModelsByFid($postsInfo['fid']);
        $tids = Arr::pluck($postsInfo['thread'], 'tid');
        $pids = Arr::pluck($postsInfo['reply'], 'pid');
        $spids = Arr::pluck($postsInfo['subReply'], 'spid');
        $threadsInfo = $tids === []
            ? collect()
            : ($isInfoOnlyContainsPostsID
                ? $postsModel['thread']->tid($tids)->orderBy($orderBy, $orderDirection)->hidePrivateFields()->get()
                : collect($postsInfo['thread'])
            );
        $repliesInfo = $pids === []
            ? collect()
            : ($isInfoOnlyContainsPostsID
                ? $postsModel['reply']->pid($pids)->orderBy($orderBy, $orderDirection)->hidePrivateFields()->get()
                : collect($postsInfo['reply'])
            );
        $subRepliesInfo = $spids === []
            ? collect()
            : ($isInfoOnlyContainsPostsID
                ? $postsModel['subReply']->spid($spids)->orderBy($orderBy, $orderDirection)->hidePrivateFields()->get()
                : collect($postsInfo['subReply'])
            );

        $isSubIDsMissInOriginIDs = fn(Collection $originIDs, Collection $subIDs): bool
        => $subIDs->contains(
            fn(int $subID): bool => ! $originIDs->contains($subID)
        );

        $tidsInReplies = $repliesInfo->pluck('tid')->concat($subRepliesInfo->pluck('tid'))->unique()->sort()->values();
        // $tids must be first argument to ensure the diffed $tidsInReplies existing
        if ($isSubIDsMissInOriginIDs(collect($tids), $tidsInReplies)) {
            // fetch complete threads info which appeared in replies and sub replies info but missed in $tids
            $threadsInfo = $postsModel['thread']
                ->tid($tidsInReplies->concat($tids)->toArray())
                ->orderBy($orderBy ?? 'tid', $orderDirection)
                ->hidePrivateFields()->get();
        }

        $pidsInThreadsAndSubReplies = $subRepliesInfo->pluck('pid');
        if ($pids === []) { // append thread's first reply when there's no pid
            $pidsInThreadsAndSubReplies = $pidsInThreadsAndSubReplies->concat($threadsInfo->pluck('firstPid'));
        }
        $pidsInThreadsAndSubReplies = $pidsInThreadsAndSubReplies->unique()->sort()->values();
        // $pids must be first argument to ensure the diffed $pidsInSubReplies existing
        if ($isSubIDsMissInOriginIDs(collect($pids), $pidsInThreadsAndSubReplies)) {
            // fetch complete replies info which appeared in threads and sub replies info but missed in $pids
            $repliesInfo = $postsModel['reply']
                ->pid($pidsInThreadsAndSubReplies->concat($pids)->toArray())
                ->orderBy($orderBy ?? 'pid', $orderDirection)
                ->hidePrivateFields()->get();
        }

        $convertJsonContentToHtml = function (?\ArrayAccess $post): ?\ArrayAccess {
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
        $repliesInfo = collect(Helper::setKeyWithItemsValue($repliesInfo, 'pid'));
        $subRepliesInfo = collect(Helper::setKeyWithItemsValue($subRepliesInfo, 'spid'));
        $nestedPostsInfo = [];

        foreach ($threadsInfo as $tid => $thread) {
            $threadReplies = $repliesInfo->where('tid', $tid)->toArray(); // can't use values() here to prevent losing posts id key
            foreach ($threadReplies as $pid => $reply) {
                // values() and array_values() remove keys to simplify json data
                $threadReplies[$pid]['subReplies'] = $subRepliesInfo->where('pid', $pid)->values()->toArray();
            }
            $nestedPostsInfo[$tid] = $thread + ['replies' => array_values($threadReplies)];
        }

        return array_values($nestedPostsInfo);
    }
}
