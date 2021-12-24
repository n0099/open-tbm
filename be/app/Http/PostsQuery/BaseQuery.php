<?php

namespace App\Http\PostsQuery;

use App\Helper;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\Post\Post;
use Illuminate\Support\Collection;

trait BaseQuery
{
    protected array $queryResult;

    protected array $queryResultPages;

    abstract public function query(QueryParams $params): self;

    public function __construct(protected int $perPageItems)
    {
    }

    public function getResult(): array
    {
        return $this->queryResult;
    }

    public function getResultPages(): array
    {
        return $this->queryResultPages;
    }

    public function fillWithParentPost(): array
    {
        $result = $this->queryResult;
        $postModels = PostModelFactory::getPostModelsByFid($result['fid']);
        $tids = array_column($result['threads'], 'tid');
        $pids = array_column($result['replies'], 'pid');
        $spids = array_column($result['subReplies'], 'spid');

        $isInfoOnlyContainsPostsID = $this instanceof IndexQuery;
        $queryDetailedPostsInfo = static function (array $postIDs, string $postType) use ($result, $postModels, $isInfoOnlyContainsPostsID) {
            if ($postIDs === []) {
                return collect();
            }
            $model = $postModels[$postType];
            return collect($isInfoOnlyContainsPostsID
                ? $model->{Helper::POSTS_TYPE_ID[$postType]}($postIDs)->hidePrivateFields()->get()->toArray()
                : $result[Helper::POST_TYPES_TO_PLURAL[$postType]]);
        };
        $threads = $queryDetailedPostsInfo($tids, 'thread');
        $replies = $queryDetailedPostsInfo($pids, 'reply');
        $subReplies = $queryDetailedPostsInfo($spids, 'subReply');

        $isSubPostIDMissFormParent = static fn (Collection $parentIDs, Collection $subIDs)
            => $subIDs->contains(static fn (int $subID) => !$parentIDs->contains($subID));

        $tidsInReplies = $replies->pluck('tid')
            ->concat($subReplies->pluck('tid'))->unique()->sort()->values();
        // $tids must be first argument to ensure the existence of diffed $tidsInReplies
        if ($isSubPostIDMissFormParent(collect($tids), $tidsInReplies)) {
            // fetch complete threads info which appeared in replies and sub replies info but missing in $tids
            $threads = collect($postModels['thread']
                ->tid($tidsInReplies->concat($tids)->toArray())
                ->hidePrivateFields()->get()->toArray());
        }

        $pidsInThreadsAndSubReplies = $subReplies->pluck('pid')
            // append thread's first reply when there's no pid
            ->concat($pids === [] ? $threads->pluck('firstPid') : [])
            ->unique()->sort()->values();
        // $pids must be first argument to ensure the diffed $pidsInSubReplies existing
        if ($isSubPostIDMissFormParent(collect($pids), $pidsInThreadsAndSubReplies)) {
            // fetch complete replies info which appeared in threads and sub replies info but missing in $pids
            $replies = collect($postModels['reply']
                ->pid($pidsInThreadsAndSubReplies->concat($pids)->toArray())
                ->hidePrivateFields()->get()->toArray());
        }

        $convertJsonContentToHtml = static function (array $post) {
            if ($post['content'] !== null) {
                $post['content'] = Post::convertJsonContentToHtml($post['content']);
            }
            return $post;
        };
        $replies->transform($convertJsonContentToHtml);
        $subReplies->transform($convertJsonContentToHtml);

        return array_merge(
            ['fid' => $result['fid']],
            array_combine(Helper::POST_TYPES_PLURAL, [$threads->toArray(), $replies->toArray(), $subReplies->toArray()])
        );
    }

    public static function nestPostsWithParent(array $threads, array $replies, array $subReplies, int $fid): array
    {
        // adding useless parameter $fid will compatible with array shape of field $this->queryResult when passing it as spread arguments
        $threads = Helper::keyBy($threads, 'tid');
        $replies = Helper::keyBy($replies, 'pid');
        $subReplies = Helper::keyBy($subReplies, 'spid');
        $nestedPostsInfo = [];

        foreach ($threads as $tid => $thread) {
            // can't invoke values() here to prevent losing key with posts id
            $threadReplies = collect($replies)->where('tid', $tid)->toArray();
            foreach ($threadReplies as $pid => $reply) {
                // values() and array_values() remove keys to simplify json data
                $threadReplies[$pid]['subReplies'] = collect($subReplies)->where('pid', $pid)->values()->toArray();
            }
            $nestedPostsInfo[$tid] = array_merge($thread, ['replies' => array_values($threadReplies)]);
        }

        return array_values($nestedPostsInfo);
    }
}
