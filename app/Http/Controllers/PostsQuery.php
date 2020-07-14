<?php

namespace App\Http\Controllers;

use App\Tieba\Eloquent\IndexModel;
use App\Helper;
use App\Tieba\Eloquent\ForumModel;
use App\Tieba\Eloquent\PostModel;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\Eloquent\UserModel;
use App\Tieba\Post\Post;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class PostsQuery extends Controller
{
    private int $pagingPerPageItems = 200;

    private array $postsAuthorUid = [];

    public function query(\Illuminate\Http\Request $request)
    {
        $paramsValidValue = [
            'orderDirection' => ['ASC', 'DESC'],
            'range' => ['<', '=', '>'],
            'userGender' => ['default', 0, 1, 2],
            'userManagerType' => ['default', 'all', 'manager', 'assist', 'voiceadmin']
        ];
        return json_encode($this->getQueryResultJson($request->validate([
            'page' => 'integer',
            'fid' => 'integer',
            'tid' => 'integer|required_with:tidRange,userExpGradeRange',
            'pid' => 'integer|required_with:pidRange',
            'spid' => 'integer|required_with:spidRange',
            'postType' => 'array',
            'orderBy' => 'string',
            'orderDirection' => Rule::in($paramsValidValue['orderDirection']),
            // below are custom query params
            'tidRange' => Rule::in($paramsValidValue['range']),
            'pidRange' => Rule::in($paramsValidValue['range']),
            'spidRange' => Rule::in($paramsValidValue['range']),
            'threadTitle' => 'string|required_with:threadTitleRegex',
            'threadTitleRegex' => 'boolean',
            'postContent' => 'string|required_with:postContentRegex',
            'postContentRegex' => 'boolean',
            'postTimeStart' => 'date|required_with:postTimeEnd',
            'postTimeEnd' => 'date|required_with:postTimeStart',
            'latestReplyTimeStart' => 'date|required_with:latestReplyTimeEnd',
            'latestReplyTimeEnd' => 'date|required_with:latestReplyTimeStart',
            'threadProperty' => 'array',
            'threadReplyNum' => 'integer|required_with:threadReplyNumRange',
            'threadReplyNumRange' => Rule::in($paramsValidValue['range']),
            'replySubReplyNum' => 'integer|required_with:replySubReplyNumRange',
            'replySubReplyNumRange' => Rule::in($paramsValidValue['range']),
            'threadViewNum' => 'integer|required_with:threadViewNumRange',
            'threadViewNumRange' => Rule::in($paramsValidValue['range']),
            'threadShareNum' => 'integer|required_with:threadShareNumRange',
            'threadShareNumRange' => Rule::in($paramsValidValue['range']),
            'userType' => 'array',
            'userID' => 'int',
            'userName' => 'string',
            'userDisplayName' => 'string',
            'userExpGrade' => 'integer',
            'userExpGradeRange' => Rule::in($paramsValidValue['range']),
            'userGender' => Rule::in($paramsValidValue['userGender']),
            'userManagerType' => Rule::in($paramsValidValue['userManagerType']),
        ])));
    }

    private function customQuery(Collection $queryParams, Collection $queryParamsName, array $queryPostType, array $queryUserType): array
    {
        $postsIDNamePair = [
            'thread' => 'tid',
            'reply' => 'pid',
            'subReply' => 'spid'
        ];

        $queryPostsID = $queryParams->only(['tid', 'pid', 'spid'])->filter()->toArray();
        $customQueryFid = $queryParams['fid']
            ?? (
            $queryPostsID == []
                ? Helper::abortAPI(40002)
                : IndexModel::where($queryPostsID)->firstOrFail(['fid'])->toArray()['fid']
            );
        $postsModel = PostModelFactory::getPostModelsByFid($customQueryFid);

        $postsQueryBuilder = [
            'thread' => [],
            'reply' => [],
            'subReply' => []
        ];

        $customQueryParamsRequiredPostTypes = [
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
            foreach ($requiredPostTypes as $requiredPostType) {
                if (isset($queryParams[$paramName])) {
                    Helper::abortAPIIf(40005, ! in_array($requiredPostType, $queryPostType, true));
                }
            }
        }

        /**
         * Apply custom query params's condition on posts model
         *
         * @param string $postType
         * @param PostModel|Builder $postModel
         * @param Collection $queryParams
         *
         * @return Builder|PostModel|null
         */
        $applyCustomConditionOnPostModel = function (string $postType, PostModel $postModel, Collection $queryParams) use ($postsModel, $queryPostType, $queryUserType) {
            if (in_array('latestReplier', $queryUserType, true)) {
                Helper::abortAPIIf(40003, ! in_array('thread', $queryPostType, true));
                $userInfoParamsExcludingLatestReplier = [
                    'userExpGrade',
                    'userExpGradeRange',
                    'userManagerType'
                ];
                Helper::abortAPIIf(40004, $queryParams->intersect($userInfoParamsExcludingLatestReplier) != []);
            }

            foreach ($queryParams as $paramName => $paramValue) {
                $applyParamsQueryOnPostModel = function () use ($queryParams, $queryUserType, $paramName, $paramValue, $postType, $postModel, $postsModel) {
                    $applyUserInfoSubQuery = function (array $userTypes, string $userInfoFieldName, $paramValue) use ($postType, $postModel, $postsModel): Builder {
                        if ($userInfoFieldName == 'uid') {
                            $uids = [$paramValue]; // query user by user defined id param directly
                        } else {
                            // cache previous user info params query for 1 mins to prevent duplicate query in a single query
                            // todo: remove this cache
                            $uids = \Cache::remember("postsQuery-UserInfoParam-{$userInfoFieldName}={$paramValue}", 1, fn() => UserModel::where($userInfoFieldName, $paramValue)->pluck('uid'));
                        }
                        foreach ($userTypes as $userType) {
                            if ($userType == 'latestReplier') {
                                if ($postType == 'thread') {
                                    $postModel = $postModel->whereIn('latestReplierUid', $uids);
                                } else {
                                    Helper::abortAPI(40003);
                                }
                            } elseif ($userType == 'author') {
                                $postModel = $postModel->whereIn('authorUid', $uids);
                            }
                        }
                        return $postModel;
                    };
                    $applyDateTimeRangeParamOnQuery = fn($postModel, string $dateTimeFieldName, string $dateTimeRangeStart, $dateTimeRangeEnd): Builder
                    => $postModel->whereBetween($dateTimeFieldName, [
                        $dateTimeRangeStart,
                        $dateTimeRangeEnd ?? Carbon::parse($dateTimeRangeStart)->addDay()->toDateTimeString()
                    ]);
                    switch ($paramName) {
                        case 'tid':
                            return $postModel->where('tid', $queryParams['tidRange'] ?? '=', $paramValue);
                            break;
                        case 'pid':
                            if ($postType == 'reply' || $postType == 'subReply') {
                                return $postModel->where('pid', $queryParams['pidRange'] ?? '=', $paramValue);
                            } else { // if post type is thread return null to prevent duplicated thread model
                                return null;
                            }
                            break;
                        case 'spid':
                            if ($postType == 'subReply') {
                                return $postModel->where('spid', $queryParams['spidRange'] ?? '=', $paramValue);
                            } else { // if post type is thread return null to prevent duplicated thread/reply model
                                return null;
                            }
                            break;
                        case 'orderBy':
                            $orderByRequiredPostType = [
                                'tid' => ['thread', 'reply', 'subReply'],
                                'pid' => ['reply', 'subReply'],
                                'spid' => ['subReply']
                            ];
                            if (in_array($postType, $orderByRequiredPostType[$paramValue], true)) {
                                return $postModel->orderBy($queryParams['orderBy'], $queryParams['orderDirection']);
                            } else {
                                return null; // if post type is not complicated with order by required, return null to prevent duplicated post model
                            }
                            break;
                        case 'threadTitle':
                            return $postModel->where([
                                'title',
                                ($queryParams['threadTitleRegex'] ?? false) ? 'REGEXP' : 'LIKE',
                                ($queryParams['threadTitleRegex'] ?? false) ? $paramValue : "%{$paramValue}%",
                            ]);
                            break;
                        case 'postContent':
                            if ($queryParams['postContentRegex'] ?? false) {
                                $postModel = $postModel->where('content', 'REGEXP', $paramValue);
                            } else {
                                $postModel = $postModel->where(function ($postModel) use ($paramValue) {
                                    foreach (explode(' ', $paramValue) as $splitedParamValue) { // split param by space char then append where cause on sql builder
                                        $postModel = $postModel->where('content', 'LIKE', "%{$splitedParamValue}%");
                                    }
                                });
                            }
                            return $postModel;
                            break;
                        case 'postTimeStart':
                            return $applyDateTimeRangeParamOnQuery($postModel, 'postTime', $paramValue, $queryParams['postTimeEnd'] ?? null);
                            break;
                        case 'latestReplyTimeStart':
                            return $applyDateTimeRangeParamOnQuery($postModel, 'latestReplyTime', $paramValue, $queryParams['latestReplyTimeEnd'] ?? null);
                            break;
                        case 'threadProperty':
                            foreach ($paramValue as $threadProperty) {
                                switch ($threadProperty) {
                                    case 'good':
                                        return $postModel->where('isGood', true);
                                        break;
                                    case 'sticky':
                                        return $postModel->whereNotNull('stickyType');
                                        break;
                                }
                            }
                            break;
                        case 'threadReplyNum':
                            return $postModel->where('replyNum', $queryParams['threadReplyNumRange'] ?? '=', $paramValue);
                            break;
                        case 'replySubReplyNum':
                            return $postModel->where('subReplyNum', $queryParams['replySubReplyNumRange'] ?? '=', $paramValue);
                            break;
                        case 'threadViewNum':
                            return $postModel->where('viewNum', $queryParams['threadViewNumRange'] ?? '=', $paramValue);
                            break;
                        case 'threadShareNum':
                            return $postModel->where('shareNum', $queryParams['threadShareNumRange'] ?? '=', $paramValue);
                            break;
                        case 'userID':
                            return $applyUserInfoSubQuery($queryUserType, 'uid', $paramValue);
                            break;
                        case 'userName':
                            return $applyUserInfoSubQuery($queryUserType, 'name', $paramValue);
                            break;
                        case 'userDisplayName':
                            return $applyUserInfoSubQuery($queryUserType, 'displayName', $paramValue);
                            break;
                        case 'userGender':
                            return $applyUserInfoSubQuery($queryUserType, 'gender', $paramValue);
                            break;
                        case 'userExpGrade':
                            if ($postType == 'thread') {
                                return $postModel->whereIn('firstPid', $postsModel['reply']->where([ // TODO: massive sub query
                                    ['floor', '=', 1],
                                    ['authorExpGrade', $queryParams['userExpGradeRange'] ?? '=', $paramValue]
                                ])->pluck('pid'));
                            } else {
                                return $postModel->where('authorExpGrade', $queryParams['userExpGradeRange'] ?? '=', $paramValue);
                            }
                            break;
                        case 'userManagerType':
                            if ($paramValue == 'all') {
                                return $postModel->whereNull('authorManagerType');
                            } else {
                                return $postModel->where('authorManagerType', $paramValue);
                            }
                            break;
                        case 'orderDirection':
                        case 'tidRange':
                        case 'pidRange':
                        case 'spidRange':
                        case 'postTimeEnd':
                        case 'latestReplyTimeEnd':
                        case 'userExpGradeRange':
                        case 'threadReplyNumRange':
                        case 'replySubReplyNumRange':
                        case 'threadViewNumRange':
                        case 'threadShareNumRange':
                        default:
                            return $postModel == null ? null : $postModel->newQuery();
                    }
                };
                $postModel = $applyParamsQueryOnPostModel();
            }
            return $postModel;
        };

        /** @var array $queryPostsTypeModel post models within query posts type **/
        $queryPostsTypeModel = $queryPostType == null
            ? $postsModel
            : collect($postsModel)->intersectByKeys(collect($queryPostType)->flip());
        foreach ($queryPostsTypeModel as $postType => $postModel) {
            $postsQueryBuilder[$postType] = $applyCustomConditionOnPostModel($postType, $postModel, $queryParams);
        }

        $customQueryDefaultOrderDirection = 'DESC';
        foreach ($postsQueryBuilder as $postType => $postQueryBuilder) {
            if ($postQueryBuilder != null) {
                if (! $queryParamsName->contains('orderBy')) { // order by post type id desc by default
                    $postQueryBuilder = $postQueryBuilder->orderBy($postsIDNamePair[$postType], $customQueryDefaultOrderDirection);
                }
                $postsQueryBuilder[$postType] = $postQueryBuilder->hidePrivateFields()->simplePaginate($this->pagingPerPageItems);
            }
        }

        $postsQueriedInfo = [
            'fid' => 0,
            'thread' => [],
            'reply' => [],
            'subReply' => []
        ];
        $postsQueriedInfo['fid'] = $customQueryFid;
        foreach ($postsIDNamePair as $postType => $postIDName) { // assign posts queried info from $postsQueryBuilder
            $postQueryBuilder = $postsQueryBuilder[$postType];
            $postsQueriedInfo[$postType] = $postQueryBuilder == null ? [] : $postQueryBuilder->toArray()['data'];
        }
        $postsQueryBuilder = array_filter($postsQueryBuilder); // array_filter() will remove falsy values

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
            return $unionStatement($unionValues == [] ? [0] : $unionValues); // prevent empty array
        };
        $pagesInfo = [
            'firstItem' => $pageInfoUnion($postsQueryBuilder, 'firstItem', fn($unionValues) => min($unionValues)),
            'currentItems' => $pageInfoUnion($postsQueryBuilder, 'count', fn($unionValues) => array_sum($unionValues)),
            'currentPage' => $pageInfoUnion($postsQueryBuilder, 'currentPage', fn($unionValues) => min($unionValues))
        ];

        return ['result' => $postsQueriedInfo, 'pages' => $pagesInfo];
    }

    private function indexQuery(Collection $queryParamsName, Collection $indexesQueryParams, Collection $queryParams, array $queryPostType): array
    {
        $postsIDNamePair = [
            'thread' => 'tid',
            'reply' => 'pid',
            'subReply' => 'spid'
        ];
        $indexesModel = IndexModel::where($indexesQueryParams->toArray());

        // if ($queryParamsName->contains('orderBy')) {
        if ($queryParams->has('orderBy')) {
            $indexesModel->orderBy($queryParams['orderBy'], $queryParams['orderDirection']);
        } elseif ($indexesQueryParams->keys()->toArray() === ['fid']) { // query by fid only
            $indexesModel->orderBy('postTime', 'DESC'); // order by postTime to prevent posts out of order when order by post id
        } else { // query by post id
            $indexesModel = $indexesModel->orderByMulti([
                'tid' => 'ASC',
                'pid' => 'ASC',
                'spid' => 'ASC'
            ]);
        }

        $indexesResults = $indexesModel->whereIn('type', $queryPostType)->simplePaginate($this->pagingPerPageItems);
        Helper::abortAPIIf(40401, $indexesResults->isEmpty());

        $postsQueriedInfo = [
            'fid' => 0,
            'thread' => [],
            'reply' => [],
            'subReply' => []
        ];
        $postsQueriedInfo['fid'] = $indexesResults->pluck('fid')->first();
        foreach ($postsIDNamePair as $postType => $postIDName) { // assign queried posts id from $indexesModel
            $postsQueriedInfo[$postType] = $indexesResults->where('type', $postType)->toArray();
        }
        return [
            'result' => $postsQueriedInfo,
            'pages' => [
                'firstItem' => $indexesResults->firstItem(),
                'currentItems' => $indexesResults->count(),
                'currentPage' => $indexesResults->currentPage()
            ]
        ];
    }

    private function getQueryResultJson(array $queryParams): array
    {
        $queryParams = collect($queryParams);
        $queryParamsName = $queryParams->keys();
        // set post and user type params default value then remove from query params
        $queryParams['postType'] = $queryParams['postType'] ?? ['thread', 'reply', 'subReply'];
        $queryParams['userType'] = $queryParams['userType'] ?? ['author'];
        $queryPostType = $queryParams->pull('postType');
        $queryUserType = $queryParams->pull('userType');
        $queryParams->pull('page');

        $indexesQueryParams = $queryParams->only(['fid', 'tid', 'pid', 'spid'])->filter(); // filter() will remove falsy values
        $isIndexQuery = ! $indexesQueryParams->isEmpty();
        $isCustomQuery = $queryParamsName->contains(function ($paramName): bool {
            $customQueryParams = collect([
                'tidRange',
                'pidRange',
                'spidRange',
                'threadTitle',
                'threadTitleRegex',
                'postContent',
                'postContentRegex',
                'postTimeStart',
                'postTimeEnd',
                'latestReplyTimeStart',
                'latestReplyTimeEnd',
                'threadProperty',
                'threadReplyNum',
                'threadReplyNumRange',
                'replySubReplyNum',
                'replySubReplyNumRange',
                'threadViewNum',
                'threadViewNumRange',
                'threadShareNum',
                'threadShareNumRange',
                'userType',
                'userID',
                'userName',
                'userDisplayName',
                'userExpGrade',
                'userExpGradeRange',
                'userGender',
                'userManagerType'
            ]);
            // does query params contains custom query params and query params have a valid fid or post ids
            return $customQueryParams->contains($paramName);
        });

        if ($isCustomQuery) {
            $queryResult = $this->customQuery($queryParams, $queryParamsName, $queryPostType, $queryUserType);
            $postsQueriedInfo = $queryResult['result'];
            $pagesInfo = $queryResult['pages'];
        } elseif ($isIndexQuery) {
            $queryResult = $this->indexQuery($queryParamsName, $indexesQueryParams, $queryParams, $queryPostType);
            // todo: temp
            $postsQueriedInfo = $queryResult['result'];
            $pagesInfo = $queryResult['pages'];
        } else {
            Helper::abortAPI(40001);
        }

        return [
            'pages' => $pagesInfo,
            'forum' => ForumModel::where('fid', $postsQueriedInfo['fid'])->hidePrivateFields()->first()->toArray(),
            'threads' => $this->getNestedPostsInfoByID(
                $postsQueriedInfo,
                $isIndexQuery,
                $queryParams['orderBy'] ?? null,
                $queryParams['orderDirection'] ?? $indexesOrderDirection ?? $customQueryDefaultOrderDirection ?? null
            ),
            'users' => UserModel::whereIn('uid', $this->postsAuthorUid)->hidePrivateFields()->get()->toArray()
        ];
    }

    private function getNestedPostsInfoByID(array $postsInfo, bool $isInfoOnlyContainsPostsID, ?string $orderBy, ?string $orderDirection): array
    {
        /** @var $postsInfo ['fid' => int, 'thread' => array, 'reply' => array, 'subReply' => array] */
        $postsModel = PostModelFactory::getPostModelsByFid($postsInfo['fid']);
        $postsInfo['thread'] = collect($postsInfo['thread']);
        $postsInfo['reply'] = collect($postsInfo['reply']);
        $postsInfo['subReply'] = collect($postsInfo['subReply']);
        $tids = $postsInfo['thread']->pluck('tid')->filter()->toArray();
        $pids = $postsInfo['reply']->pluck('pid')->filter()->toArray();
        $spids = $postsInfo['subReply']->pluck('spid')->filter()->toArray();
        $threadsInfo = $tids === []
            ? collect()
            : ($isInfoOnlyContainsPostsID
                ? $postsModel['thread']->tid($tids)->orderBy($orderBy ?? 'tid', $orderDirection)->hidePrivateFields()->get()
                : $postsInfo['thread']
            );
        $repliesInfo = $pids === []
            ? collect()
            : ($isInfoOnlyContainsPostsID
                ? $postsModel['reply']->pid($pids)->orderBy($orderBy ?? 'pid', $orderDirection)->hidePrivateFields()->get()
                : $postsInfo['reply']
            );
        $subRepliesInfo = $spids === []
            ? collect()
            : ($isInfoOnlyContainsPostsID
                ? $postsModel['subReply']->spid($spids)->orderBy($orderBy ?? 'spid', $orderDirection)->hidePrivateFields()->get()
                : $postsInfo['subReply']
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

        $this->postsAuthorUid = collect([
            $threadsInfo->pluck('authorUid'),
            $threadsInfo->pluck('latestReplierUid'),
            $repliesInfo->pluck('authorUid'),
            $subRepliesInfo->pluck('authorUid')
        ])->flatten()->unique()->sort()->values()->toArray();
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
                // values() and array_values remove keys to simplify json data
                $threadReplies[$pid]['subReplies'] = $subRepliesInfo->where('pid', $pid)->values()->toArray();
            }
            $nestedPostsInfo[$tid] = $thread + ['replies' => array_values($threadReplies)];
        }

        return array_values($nestedPostsInfo);
    }
}
