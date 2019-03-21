<?php

namespace App\Http\Controllers;

use App\Tieba\Eloquent\IndexModel;
use App\Helper;
use App\Tieba\Eloquent\ForumModel;
use App\Tieba\Eloquent\PostModel;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\Eloquent\ReplyModel;
use App\Tieba\Eloquent\UserModel;
use App\Tieba\Post\Post;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use function GuzzleHttp\json_encode;
use Illuminate\Validation\Rule;

class PostsQueryController extends Controller
{
    private $pagingPerPageItems = 200;

    private $postsAuthorUids = [];

    private function getNestedPostsInfoByIDs(array $postsInfo, bool $isInfoOnlyContainsPostsID, $orderBy, $orderDirection): array
    {
        $postsModel = PostModelFactory::getPostsModelByForumID($postsInfo['fid']);
        $postsInfo['thread'] = collect($postsInfo['thread']);
        $postsInfo['reply'] = collect($postsInfo['reply']);
        $postsInfo['subReply'] = collect($postsInfo['subReply']);
        $tids = $postsInfo['thread']->pluck('tid');
        $pids = $postsInfo['reply']->pluck('pid');
        $spids = $postsInfo['subReply']->pluck('spid');
        $threadsInfo = empty($tids)
            ? collect()
            : ($isInfoOnlyContainsPostsID
                ? $postsModel['thread']->tid($tids->toArray())->orderBy($orderBy ?? 'tid', $orderDirection)->hidePrivateFields()->get()
                : $postsInfo['thread']
            );
        $repliesInfo = empty($pids)
            ? collect()
            : ($isInfoOnlyContainsPostsID
                ? $postsModel['reply']->pid($pids->toArray())->orderBy($orderBy ?? 'pid', $orderDirection)->hidePrivateFields()->get()
                : $postsInfo['reply']
            );
        $subRepliesInfo = empty($spids)
            ? collect()
            : ($isInfoOnlyContainsPostsID
                ? $postsModel['subReply']->spid($spids->toArray())->orderBy($orderBy ?? 'spid', $orderDirection)->hidePrivateFields()->get()
                : $postsInfo['subReply']
            );
        $isSubIDsMissInOriginIDs = function (Collection $originIDs, Collection $subIDs): bool {
            return $subIDs->contains(function ($subID) use ($originIDs): bool {
                return ! $originIDs->contains($subID);
            });
        };

        $threadsIDInReplies = $repliesInfo->pluck('tid')->concat($subRepliesInfo->pluck('tid'))->unique()->sort()->values();
        // $tids must be first argument to ensure the diffed $threadsIDInReplies existing
        if ($isSubIDsMissInOriginIDs($tids, $threadsIDInReplies)) {
            // fetch complete threads info which appeared in replies and sub replies info but missed in $tids
            $threadsInfo = $postsModel['thread']
                ->tid($threadsIDInReplies->concat($tids)->toArray())
                ->orderBy($orderBy ?? 'tid', $orderDirection)
                ->hidePrivateFields()->get();
        }

        $repliesIDInThreadsAndSubReplies = $subRepliesInfo->pluck('pid');
        if (empty($pids)) { // append thread's first reply when there's no pid
            $repliesIDInThreadsAndSubReplies = $repliesIDInThreadsAndSubReplies->concat($threadsInfo->pluck('firstPid'));
        }
        $repliesIDInThreadsAndSubReplies = $repliesIDInThreadsAndSubReplies->unique()->sort()->values();
        // $pids must be first argument to ensure the diffed $repliesIDInSubReplies existing
        if ($isSubIDsMissInOriginIDs($pids, $repliesIDInThreadsAndSubReplies)) {
            // fetch complete replies info which appeared in threads and sub replies info but missed in $pids
            $repliesInfo = $postsModel['reply']
                ->pid($repliesIDInThreadsAndSubReplies->concat($pids)->toArray())
                ->orderBy($orderBy ?? 'pid', $orderDirection)
                ->hidePrivateFields()->get();
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
                $post['content'] = trim(str_replace("\n", '', Post::convertJsonContentToHtml($post['content'])));
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
        return static::convertNestedPostsInfo($threadsInfo->toArray(), $repliesInfo->toArray(), $subRepliesInfo->toArray());
    }

    private static function convertNestedPostsInfo(array $threadsInfo = [], array $repliesInfo = [], array $subRepliesInfo = []): array
    {
        $threadsInfo = Helper::convertIDListKey($threadsInfo, 'tid');
        $repliesInfo = collect(Helper::convertIDListKey($repliesInfo, 'pid'));
        $subRepliesInfo = collect(Helper::convertIDListKey($subRepliesInfo, 'spid'));
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

            $postsQueryBuilder = [
                'thread' => [],
                'reply' => [],
                'subReply' => []
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
                        $applyUserInfoParamSubQuery = function (array $userTypes, string $userInfoParamName, $userInfoParamValue) use ($postType, $postModel, $postsModel): Builder {
                            $userIDs = \Cache::remember("postsQuery-UserInfoParam-{$userInfoParamName}={$userInfoParamValue}", 1, function () use ($userInfoParamName, $userInfoParamValue) {
                                return UserModel::where($userInfoParamName, $userInfoParamValue)->pluck('uid'); // cache previous user info params query for 1 mins to prevent duplicate query in a single query
                            });
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
                            case 'userName':
                                return $applyUserInfoParamSubQuery($queryUserType, 'name', $paramValue);
                                break;
                            case 'userDisplayName':
                                return $applyUserInfoParamSubQuery($queryUserType, 'displayName', $paramValue);
                                break;
                            case 'userGender':
                                return $applyUserInfoParamSubQuery($queryUserType, 'gender', $paramValue);
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
                $postsQueryBuilder[$postType] = $applyCustomConditionOnPostModel($postType, $postModel, $queryParams);
            }

            $customQueryDefalutOrderDirection = 'DESC';
            foreach ($postsQueryBuilder as $postType => $postQueryBuilder) {
                if ($postQueryBuilder != null) {
                    if (! $queryParamsName->contains('orderBy')) { // order by post type id desc by default
                        $postQueryBuilder = $postQueryBuilder->orderBy($postsIDNamePair[$postType], $customQueryDefalutOrderDirection);
                    }
                    $postsQueryBuilder[$postType] = $postQueryBuilder->hidePrivateFields()->paginate($this->pagingPerPageItems);
                }
            }

            $postsQueriedInfo['fid'] = $customQueryForumID;
            foreach ($postsIDNamePair as $postType => $postIDName) { // assign posts queried info from $postsQueryBuilder
                $postQueryBuilder = $postsQueryBuilder[$postType];
                $postsQueriedInfo[$postType] = $postQueryBuilder == null ? [] : $postQueryBuilder->toArray()['data'];
            }
            $postsQueryBuilder = array_filter($postsQueryBuilder); // array_filter() will remove falsy(like null) value elements

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
                'firstItem' => $pageInfoUnion($postsQueryBuilder, 'firstItem', function ($unionValues) {
                    return min($unionValues);
                }),
                'currentItems' => $pageInfoUnion($postsQueryBuilder, 'count', function ($unionValues) {
                    return array_sum($unionValues);
                }),
                'totalItems' => $pageInfoUnion($postsQueryBuilder, 'total', function ($unionValues) {
                    return array_sum($unionValues);
                }),
                /*'perPageItems' => $pageInfoUnion($postsQueryBuilder, 'perPage', function ($unionValues) {
                    return min($unionValues);
                }),*/
                'currentPage' => $pageInfoUnion($postsQueryBuilder, 'currentPage', function ($unionValues) {
                    return min($unionValues);
                }),
                'lastPage' => $pageInfoUnion($postsQueryBuilder, 'lastPage', function ($unionValues) {
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
                    'spid' => $indexesOrderDirection,
                    'pid' => $indexesOrderDirection,
                    'tid' => $indexesOrderDirection
                ];
                $indexesModel = $indexesModel->orderByMulti($indexesOrderBy);
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

        $nestedPostsInfo = $this->getNestedPostsInfoByIDs(
            $postsQueriedInfo,
            ! $isCustomQuery,
            $queryParams['orderBy'] ?? null,
            $queryParams['orderDirection'] ?? $indexesOrderDirection ?? $customQueryDefalutOrderDirection ?? null
        );
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
        $paramsValidValue = [
            'orderDirection' => ['ASC', 'DESC'],
            'range' => ['<', '=', '>'],
            'userGender' => ['default', 0, 1, 2],
            'userManagerType' => ['default', 'all', 'manager', 'assist', 'voiceadmin']
        ];
        return $this->getQueryResultJson($request->validate([
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
            'userName' => 'string',
            'userDisplayName' => 'string',
            'userExpGrade' => 'integer',
            'userExpGradeRange' => Rule::in($paramsValidValue['range']),
            'userGender' => Rule::in($paramsValidValue['userGender']),
            'userManagerType' => Rule::in($paramsValidValue['userManagerType']),
        ]));
    }
}
