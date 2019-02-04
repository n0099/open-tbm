<?php

namespace App\Http\Controllers;

use App\Eloquent\IndexModel;
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
    private $postsAuthorUids = [];

    protected static function convertIDListKey(array $list, string $keyName): array
    {
        // same with \App\Jobs\CrawlerQueue::convertIDListKey()
        $newList = [];

        foreach ($list as $item) {
            $newList[$item[$keyName]] = $item;
        }
        ksort($newList);

        return $newList;
    }

    private function getNestedPostsInfoByIDs(int $fid, array $tids = [], array $pids = [], array $spids = []): array
    {
        $postsModel = PostModelFactory::getPostsModelByForumID($fid);
        $threadsInfo = empty($tids) ? collect() : $postsModel['thread']->tid($tids)->hidePrivateFields()->get();
        $repliesInfo = empty($pids) ? collect() : $postsModel['reply']->pid($pids)->hidePrivateFields()->get();
        $subRepliesInfo = empty($spids) ? collect() : $postsModel['subReply']->spid($spids)->hidePrivateFields()->get();

        $isSubIDsMissInOriginIDs = function (Collection $originIDs, Collection $subIDs): bool {
            return $subIDs->contains(function ($subID) use ($originIDs): bool {
                return ! $originIDs->contains($subID);
            });
        };
        $threadsIDInReplies = $repliesInfo->pluck('tid')->concat($subRepliesInfo->pluck('tid'));
        $threadsIDInReplies = $threadsIDInReplies->unique()->sort()->values();
        // $tids must be first argument to ensure the diffed $threadsIDInReplies existing
        if ($isSubIDsMissInOriginIDs(collect($tids), $threadsIDInReplies)) {
            // fetch complete threads info which appeared in replies and sub replies info but missed in $tids
            $threadsInfo = $postsModel['thread']->tid($threadsIDInReplies->union($tids)->toArray())->hidePrivateFields()->get();
        }
        $repliesIDInThreadsAndSubReplies = $subRepliesInfo->pluck('pid');
        if (empty($pids)) {
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

        $convertJsonContentToHtml = function (\App\Tieba\Eloquent\PostModel $post): array {
            $post->content = trim(str_replace("\n", '', \App\Tieba\Reply::convertJsonContentToHtml($post->content)));
            return $post->toArray();
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
            $threadReplies = $repliesInfo->where('tid', $tid)->toArray();
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
        $customQueryParams = collect([
            //'fid',
            'postType',
            'tidRange',
            'pidRange',
            'spidRange',
            'threadTitle',
            'threadTitletRegex',
            'postContent',
            'postContentRegex',
            /*'orderBy',
            'orderDirection'*/
            //'userType',
            'userName',
            'userDisplayName',
            'userExpGrade',
            'userExpGradeRange',
            'userGender',
            'userManagerType',
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
            'threadShareNumRange'
        ]);
        $orderByRequiredPostType = [
            'tid' => ['thread', 'reply', 'subReply'],
            'pid' => ['reply', 'subReply'],
            'spid' => ['subReply']
        ];
        if ($queryParamsName->contains('orderBy')) {
            foreach ($queryPostType ?? array_keys($postsIDNamePair) as $postType) {
                abort_if(! in_array($postType, $orderByRequiredPostType[$queryParams['orderBy']]), 400, 'Post types doesn\'t exists in order by field required types');
            }
        }

        $resultPostsID = [
            'fid' => 0,
            'tid' => [],
            'pid' => [],
            'spid' => []
        ];

        if ($queryParamsName->contains(function ($paramName) use ($customQueryParams): bool {
            // does query params contains custom query params and query params have a valid fid or post ids
            return $customQueryParams->contains($paramName);
        })) {
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

            debug($queryPostType);
            debug($queryPostsBuilder);

            foreach ($queryPostsBuilder as $postType => $postQueryBuilder) {
                if ($postQueryBuilder != null) {
                    if ($queryParamsName->contains('orderBy')) {
                        $postQueryBuilder = $postQueryBuilder->orderBy($queryParams['orderBy'], $queryParams['orderDirection']);
                    } else {
                        $postQueryBuilder = $postQueryBuilder->orderBy('postTime', 'DESC');
                    }
                    $queryPostsBuilder[$postType] = $postQueryBuilder->hidePrivateFields()->paginate(200);
                }
            }

            debug($queryPostsBuilder);

            $resultPostsID['fid'] = $customQueryForumID;
            foreach ($postsIDNamePair as $postType => $postIDName) { // assign posts ids from $queryPostsBuilder
                $resultPostsID[$postIDName] = ($queryPostsBuilder[$postType] == null
                    ? []
                    : $queryPostsBuilder[$postType]->pluck($postIDName)->toArray());
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
                /*'perPageItem' => $pageInfoUnion($queryPostsBuilder, 'perPage', function ($unionValues) {
                    return min($unionValues);
                }),*/
                'currentPage' => $pageInfoUnion($queryPostsBuilder, 'currentPage', function ($unionValues) {
                    return min($unionValues);
                }),
                'lastPage' => $pageInfoUnion($queryPostsBuilder, 'lastPage', function ($unionValues) {
                    return max($unionValues);
                })
            ];
        } elseif (! $queryParamsName->flip()->only(['tid', 'pid', 'spid'])->isEmpty()) {
            $indexesModel = IndexModel::where($queryParams->only(['tid', 'pid', 'spid'])->filter()->toArray()); // filter() will remove falsy(like null) value elements

            //abort_if(! isset($indexesModel), 400);
            if ($queryParamsName->contains('orderBy')) {
                $indexesModel->orderBy($queryParams['orderBy'], $queryParams['orderDirection']);
            } else {
                $indexesOrderBy = [
                    'tid' => 'ASC',
                    'pid' => 'ASC',
                    'spid' => 'ASC',
                    'postTime' => 'ASC',
                ];
                foreach ($indexesOrderBy as $orderFieldName => $orderDirection) {
                    $indexesModel = $indexesModel->orderBy($orderFieldName, $orderDirection);
                }
            }
            $indexesModel = $indexesModel->whereIn('type', $queryPostType ?? array_keys($postsIDNamePair))->paginate(200);
            abort_if($indexesModel->isEmpty(), 404);

            $resultPostsID['fid'] = $indexesModel->pluck('fid')->first();
            foreach ($postsIDNamePair as $postType => $postIDName) { // assist posts ids from $indexesModel
                $resultPostsID[$postIDName] = $indexesModel->where('type', $postType)->pluck($postIDName)->toArray();
            }
            $pagesInfo = [
                'firstItem' => $indexesModel->firstItem(),
                'currentItems' => $indexesModel->count(),
                'totalItems' => $indexesModel->total(),
                //'perPageItem' => $indexesModel->perPage(),
                'currentPage' => $indexesModel->currentPage(),
                'lastPage' => $indexesModel->lastPage()
            ];
        } else {
            abort(400, 'Query type is neither post id nor custom query');
        }

        debug($pagesInfo);

        $nestedPostsInfo = self::getNestedPostsInfoByIDs($resultPostsID['fid'], $resultPostsID['tid'], $resultPostsID['pid'], $resultPostsID['spid']);
        $usersInfo = UserModel::whereIn('uid', $this->postsAuthorUids)->hidePrivateFields()->get()->toArray();
        $json = json_encode([
            'pages' => $pagesInfo,
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
