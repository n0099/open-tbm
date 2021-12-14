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

    abstract public function toNestedPosts(): array;

    abstract public function query(QueryParams $queryParams): self;

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

    private static function getNestedPostsInfoByID(array $postsInfo, bool $isInfoOnlyContainsPostsID): array
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

        $isSubIDsMissInOriginIDs = static fn (Collection $originIDs, Collection $subIDs): bool
        => $subIDs->contains(static fn (int $subID): bool => !$originIDs->contains($subID));

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

        $convertJsonContentToHtml = static function (array $post) {
            if ($post['content'] !== null) {
                $post['content'] = Post::convertJsonContentToHtml($post['content']);
            }
            return $post;
        };
        $repliesInfo->transform($convertJsonContentToHtml);
        $subRepliesInfo->transform($convertJsonContentToHtml);

        return self::convertNestedPostsInfo($threadsInfo->toArray(), $repliesInfo->toArray(), $subRepliesInfo->toArray());
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
