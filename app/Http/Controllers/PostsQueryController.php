<?php

namespace App\Http\Controllers;

use App\Eloquent\IndexModel;
use App\Tieba\Eloquent\ForumModel;
use App\Tieba\Eloquent\PostModel;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\Eloquent\ReplyModel;
use App\Tieba\Eloquent\SubReplyModel;
use App\Tieba\Eloquent\ThreadModel;
use App\Tieba\Eloquent\UserModel;
use Carbon\Carbon;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PostsQueryController extends Controller
{
    private $pagingPerPageItems = 200;

    private $postsAuthorUids = [];

    protected static function convertIDListKey(array $list, string $keyName): array
    {
        // same with \App\Jobs\CrawlerQueue::convertIDListKey()
        $newList = [];

        foreach ($list as $item) {
            $newList[$item[$keyName]] = $item;
        }
        //ksort($newList); // NEVER SORT POSTS ID TO PREVENT REORDER QUERIED POSTS ORDER

        return $newList;
    }

    private function getNestedPostsInfoByIDs(array $postsInfo, bool $isInfoOnlyContainsPostsID): array
    {
        $postsModel = PostModelFactory::getPostsModelByForumID($postsInfo['fid']);
        $postsInfo['thread'] = collect($postsInfo['thread']);
        $postsInfo['reply'] = collect($postsInfo['reply']);
        $postsInfo['subReply'] = collect($postsInfo['subReply']);
        $tids = $postsInfo['thread']->pluck('tid');
        $pids = $postsInfo['reply']->pluck('pid');
        $spids = $postsInfo['subReply']->pluck('spid');
        $threadsInfo = empty($tids) ? collect() : ($isInfoOnlyContainsPostsID ? $postsModel['thread']->tid($tids->toArray())->hidePrivateFields()->get() : $postsInfo['thread']);
        $repliesInfo = empty($pids) ? collect() : ($isInfoOnlyContainsPostsID ? $postsModel['reply']->pid($pids->toArray())->hidePrivateFields()->get() : $postsInfo['reply']);
        $subRepliesInfo = empty($spids) ? collect() : ($isInfoOnlyContainsPostsID ? $postsModel['subReply']->spid($spids->toArray())->hidePrivateFields()->get() : $postsInfo['subReply']);

        $isSubIDsMissInOriginIDs = function (Collection $originIDs, Collection $subIDs): bool {
            return $subIDs->contains(function ($subID) use ($originIDs): bool {
                return ! $originIDs->contains($subID);
            });
        };
        $threadsIDInReplies = $repliesInfo->pluck('tid')->concat($subRepliesInfo->pluck('tid'))->unique()->sort()->values();
        // $tids must be first argument to ensure the diffed $threadsIDInReplies existing
        if ($isSubIDsMissInOriginIDs(collect($tids), $threadsIDInReplies)) {
            // fetch complete threads info which appeared in replies and sub replies info but missed in $tids
            $threadsInfo = $postsModel['thread']->tid($threadsIDInReplies->union($tids)->toArray())->hidePrivateFields()->get();
        }
        $repliesIDInThreadsAndSubReplies = $subRepliesInfo->pluck('pid');
        if (empty($pids)) { // append thread's first reply when there's no pid
            $repliesIDInThreadsAndSubReplies = $repliesIDInThreadsAndSubReplies->concat($threadsInfo->pluck('firstPid'));
        }
        $repliesIDInThreadsAndSubReplies = $repliesIDInThreadsAndSubReplies->unique()->sort()->values();
        // $pids must be first argument to ensure the diffed $repliesIDInSubReplies existing
        if ($isSubIDsMissInOriginIDs(collect($pids), $repliesIDInThreadsAndSubReplies)) {
            // fetch complete replies info which appeared in threads and sub replies info but missed in $pids
            $repliesInfo = $postsModel['reply']->pid($repliesIDInThreadsAndSubReplies->union($pids)->toArray())->hidePrivateFields()->get();
        }

        // same functional with above
        /*if ($threadsInfo->isEmpty()) { // fetch thread info when reply and
            if ($repliesInfo->isNotEmpty()) { // query params only have reply
                $threadsInfo = $postsModel['thread']->tid($repliesInfo->pluck('tid')->toArray())->get();
            } elseif ($subRepliesInfo->isNotEmpty()) {
                $threadsInfo = $postsModel['thread']->tid($subRepliesInfo->pluck('tid')->toArray())->get();
            } else {
                throw new \InvalidArgumentException('Posts IDs is empty');
            }
        }
        if ($repliesInfo->isEmpty()) {
            $repliesInfo = $postsModel['reply']->pid($subRepliesInfo->pluck('pid')->toArray())->get();
        }
        if ($subRepliesInfo->isEmpty()) {
            $subRepliesInfo = $postsModel['subReply']->spid($repliesInfo->pluck('spid')->toArray())->get();
        }*/

        $convertJsonContentToHtml = function ($post) {
            if ($post['content'] != null) {
                $post['content'] = trim(str_replace("\n", '', \App\Tieba\Reply::convertJsonContentToHtml($post['content'])));
            }
            return $post;
        };
        $repliesInfo->transform($convertJsonContentToHtml);
        $subRepliesInfo->transform($convertJsonContentToHtml);

        $this->postsAuthorUids = collect([
            $threadsInfo->pluck('authorUid'),
            $threadsInfo->pluck('latestReplierUid'),
            $repliesInfo->pluck('authorUid'),
            $subRepliesInfo->pluck('authorUid')
        ])->flatten()->unique()->sort()->values()->toArray();
        return self::convertNestedPostsInfo($threadsInfo->toArray(), $repliesInfo->toArray(), $subRepliesInfo->toArray());
    }

    private static function convertNestedPostsInfo(array $threadsInfo = [], array $repliesInfo = [], array $subRepliesInfo = []): array
    {
        $threadsInfo = self::convertIDListKey($threadsInfo, 'tid');
        $repliesInfo = collect(self::convertIDListKey($repliesInfo, 'pid'));
        $subRepliesInfo = collect(self::convertIDListKey($subRepliesInfo, 'spid'));
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

    private function getQueryResultJson(array $queryParams): string
    {
        $queryParams = collect($queryParams);
        $queryParams->shift(); // remove first query url in parameters
        $queryParamsName = $queryParams->keys();
        // set post and user type params default value then remove from query params
        $queryParams['postType'] = $queryParams['postType'] ?? ['thread', 'reply', 'subReply'];
        $queryParams['userType'] = $queryParams['userType'] ?? ['author'];
        $queryPostType = $queryParams->pull('postType');
        $queryUserType = $queryParams->pull('userType');
        $queryParams->pull('page');

        $postsIDNamePair = [
            'thread' => 'tid',
            'reply' => 'pid',
            'subReply' => 'spid'
        ];

        $postsQueriedInfo = [
            'fid' => 0,
            'thread' => [],
            'reply' => [],
            'subReply' => []
        ];

        $indexesQueryParams = $queryParams->only(['fid', 'tid', 'pid', 'spid'])->filter(); // filter() will remove falsy(like null) value elements
        $isIndexQuery = ! $indexesQueryParams->isEmpty();
        $isCustomQuery = $queryParamsName->contains(function ($paramName): bool {
            $customQueryParams = collect([
                'postType',
                'tidRange',
                'pidRange',
                'spidRange',
                'threadTitle',
                'threadTitletRegex',
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
            $queryPostsID = $queryParams->only(['tid', 'pid', 'spid'])->filter()->toArray();
            $customQueryForumID = $queryParams['fid']
                ?? ($queryPostsID == [] ? abort(400, 'Custom query need a forum id') : IndexModel::where($queryPostsID)->firstOrFail(['fid'])->toArray()['fid']);
            $postsModel = PostModelFactory::getPostsModelByForumID($customQueryForumID);

            $queryPostsBuilder = [
                'thread' => null,
                'reply' => null,
                'subReply' => null
            ];

            $paramsRequiredPostType = [
                'threadTitle' => ['thread'],
                'latestReplyTimeStart' => ['thread'],
                //'latestReplyTimeEnd' => ['thread'],
                'threadProperty' => ['thread'],
                'threadReplyNum' => ['thread'],
                'replySubReplyNum' => ['reply'],
                'threadViewNum' => ['thread'],
                'threadShareNum' => ['thread'],
            ];
            foreach ($paramsRequiredPostType as $paramName => $postType) {
                abort_if(isset($queryParams[$paramName]) && array_diff($queryPostType, $postType) != [], 400, 'Querying post types doesn\'t fit with custom query params required types');
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
                if (in_array('latestReplier', $queryUserType)) {
                    abort_if(! in_array('thread', $queryPostType), 400, 'Query post type must contain thread when querying with latest replier user info');
                    $userInfoParamsExcludingLatestReplier = [
                        'userExpGrade',
                        'userExpGradeRange',
                        'userManagerType'
                    ];
                    if ($queryParams->intersect($userInfoParamsExcludingLatestReplier) != []) {
                        abort(400, 'Can\'t query some of latest replier\'s user info');
                    }
                }

                foreach ($queryParams as $paramName => $paramValue) {
                    $applyParamsQueryOnPostModel = function () use ($queryParams, $queryUserType, $paramName, $paramValue, $postType, $postModel, $postsModel) {
                        $applyUserInfoParamSubQuery = function (array $userTypes, iterable $userIDs) use ($postType, $postModel, $postsModel): Builder {
                            foreach ($userTypes as $userType) {
                                if ($userType == 'latestReplier') {
                                    if ($postType == 'thread') {
                                        $postModel = $postModel->whereIn('latestReplierUid', $userIDs);
                                    } else {
                                        abort(400, 'Querying with latest replier user info require query with thread post type');
                                    }
                                } elseif ($userType == 'author') {
                                    $postModel = $postModel->whereIn('authorUid', $userIDs);
                                }
                            }
                            return $postModel;
                        };
                        $applyDateTimeRangeParamOnQuery = function ($postModel, string $dateTimeFieldName, string $dateTimeRangeStart, $dateTimeRangeEnd): Builder {
                            return $postModel->whereBetween($dateTimeFieldName, [
                                $dateTimeRangeStart,
                                $dateTimeRangeEnd ?? Carbon::parse($dateTimeRangeStart)->addDay()->toDateTimeString()
                            ]);
                        };
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
                                if (in_array($postType, $orderByRequiredPostType[$paramValue])) {
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
                                if ($postType == 'thread') {
                                    // use Builder->setModel() to change query builder's model without losing previous queries conditions
                                    $postModel = $postModel->setModel($postsModel['reply'])->where('floor', '=', 1);
                                } elseif ($postType == 'reply') {
                                    $postModel = $postModel->where('floor', '!=', 1);
                                }

                                if (($queryParams['postContentRegex'] ?? false) == true) {
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
                                            return $postModel->where('isSticky', true);
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
                            case 'userName':
                                return $applyUserInfoParamSubQuery($queryUserType, UserModel::where('name', $paramValue)->pluck('uid')); // TODO: might cause duplicated uid query
                                break;
                            case 'userDisplayName':
                                return $applyUserInfoParamSubQuery($queryUserType, UserModel::where('displayName', $paramValue)->pluck('uid'));
                                break;
                            case 'userGender':
                                return $applyUserInfoParamSubQuery($queryUserType, UserModel::where('gender', $paramValue)->pluck('uid'));
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
                                if ($paramValue == 'NULL') {
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
                                //abort(400, 'Can\'t querying with undefined params');
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
                $queryPostsBuilder[$postType] = $applyCustomConditionOnPostModel($postType, $postModel, $queryParams);
            }

            foreach ($queryPostsBuilder as $postType => $postQueryBuilder) {
                if ($postQueryBuilder != null) {
                    if (! $queryParamsName->contains('orderBy')) {
                        $postQueryBuilder = $postQueryBuilder->orderBy('postTime', 'DESC');
                    }
                    $queryPostsBuilder[$postType] = $postQueryBuilder->hidePrivateFields()->paginate($this->pagingPerPageItems);
                }
            }

            $postsQueriedInfo['fid'] = $customQueryForumID;
            foreach ($postsIDNamePair as $postType => $postIDName) { // assign posts queried info from $queryPostsBuilder
                $postsQueriedInfo[$postType] = $queryPostsBuilder[$postType] == null ? [] : $queryPostsBuilder[$postType]->toArray()['data'];
            }

            $queryPostsBuilder = array_filter($queryPostsBuilder); // array_filter() will remove falsy(like null) value elements

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
                $unionValues = array_filter($unionValues); // array_filter() will remove falsy(like null) value elements
                return $unionStatement($unionValues == [] ? [0] : $unionValues); // prevent empty array
            };
            $pagesInfo = [
                'firstItem' => $pageInfoUnion($queryPostsBuilder, 'firstItem', function ($unionValues) {
                    return min($unionValues);
                }),
                'currentItems' => $pageInfoUnion($queryPostsBuilder, 'count', function ($unionValues) {
                    return array_sum($unionValues);
                }),
                'totalItems' => $pageInfoUnion($queryPostsBuilder, 'total', function ($unionValues) {
                    return array_sum($unionValues);
                }),
                /*'perPageItems' => $pageInfoUnion($queryPostsBuilder, 'perPage', function ($unionValues) {
                    return min($unionValues);
                }),*/
                'currentPage' => $pageInfoUnion($queryPostsBuilder, 'currentPage', function ($unionValues) {
                    return min($unionValues);
                }),
                'lastPage' => $pageInfoUnion($queryPostsBuilder, 'lastPage', function ($unionValues) {
                    return max($unionValues);
                })
            ];
        } elseif ($isIndexQuery) {
            $indexesModel = IndexModel::where($indexesQueryParams->toArray());

            //abort_if(! isset($indexesModel), 400);
            if ($queryParamsName->contains('orderBy')) {
                $indexesModel->orderBy($queryParams['orderBy'], $queryParams['orderDirection']);
            } else {
                $indexesOrderDirection = $indexesQueryParams->keys()->diff(['fid'])->isEmpty() ? 'DESC' : 'ASC'; // using descending order when querying only with forum id
                $indexesOrderBy = [
                    'tid' => $indexesOrderDirection,
                    'pid' => $indexesOrderDirection,
                    'spid' => $indexesOrderDirection,
                    'postTime' => $indexesOrderDirection
                ];
                foreach ($indexesOrderBy as $orderFieldName => $orderDirection) {
                    $indexesModel = $indexesModel->orderBy($orderFieldName, $orderDirection);
                }
            }
            $indexesModel = $indexesModel->whereIn('type', $queryPostType)->paginate($this->pagingPerPageItems);
            abort_if($indexesModel->isEmpty(), 404);

            $postsQueriedInfo['fid'] = $indexesModel->pluck('fid')->first();
            foreach ($postsIDNamePair as $postType => $postIDName) { // assign queried posts ids from $indexesModel
                $postsQueriedInfo[$postType] = $indexesModel->where('type', $postType)->toArray();
            }

            $pagesInfo = [
                'firstItem' => $indexesModel->firstItem(),
                'currentItems' => $indexesModel->count(),
                'totalItems' => $indexesModel->total(),
                //'perPageItems' => $indexesModel->perPage(),
                'currentPage' => $indexesModel->currentPage(),
                'lastPage' => $indexesModel->lastPage()
            ];
        } else {
            abort(400, 'Query type is neither post id nor custom query');
        }

        $nestedPostsInfo = self::getNestedPostsInfoByIDs($postsQueriedInfo, ! $isCustomQuery);
        $forumInfo = ForumModel::where('fid', $postsQueriedInfo['fid'])->hidePrivateFields()->first()->toArray();
        $usersInfo = UserModel::whereIn('uid', $this->postsAuthorUids)->hidePrivateFields()->get()->toArray();
        $json = json_encode([
            'pages' => $pagesInfo,
            'forum' => $forumInfo,
            'threads' => $nestedPostsInfo,
            'users' => $usersInfo
        ]);
        return $json;
    }

    public function query(\Illuminate\Http\Request $request)
    {
        return $this->getQueryResultJson($request->query());
    }
}
