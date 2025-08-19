<?php

namespace App\PostsQuery;

use App\DTO\Post\SortablePost;
use App\DTO\PostKey\Reply as ReplyKey;
use App\DTO\PostKey\SubReply as SubReplyKey;
use App\DTO\PostKey\Thread as ThreadKey;
use App\Entity\Post\Content\ReplyContent;
use App\Entity\Post\Content\SubReplyContent;
use App\DTO\Post\Reply;
use App\DTO\Post\SubReply;
use App\DTO\Post\Thread;
use App\Utils;
use App\Repository\Post\Content\ReplyContentRepository;
use App\Repository\Post\Content\SubReplyContentRepository;
use App\Repository\Post\PostRepositoryFactory;
use Illuminate\Support\Collection;
use Symfony\Component\Stopwatch\Stopwatch;

/** @psalm-import-type PostsKeyByTypePluralName from CursorCodec */
readonly class PostsTree
{
    /** @var Collection<int, Thread> */
    public Collection $threads;
    /** @var Collection<int, Reply> */
    public Collection $replies;
    /** @var Collection<int, SubReply> */
    public Collection $subReplies;

    public function __construct(
        private Stopwatch $stopwatch,
        private PostRepositoryFactory $postRepositoryFactory,
        private ReplyContentRepository $replyContentRepository,
        private SubReplyContentRepository $subReplyContentRepository,
    ) {}

    /**
     * @return array{
     *     matchQueryPostCount: array{thread?: int, reply?: int, subReply?: int},
     *     notMatchQueryParentPostCount: array{thread: int, reply: int},
     * }
     */
    public function fillWithParentPost(QueryResult $result): array
    {
        /** @var Collection<int> $tids */
        $tids = $result->threads->map(fn(ThreadKey $postKey) => $postKey->postId);
        /** @var Collection<int> $pids */
        $pids = $result->replies->map(fn(ReplyKey $postKey) => $postKey->postId);
        /** @var Collection<int> $spids */
        $spids = $result->subReplies->map(fn(SubReplyKey $postKey) => $postKey->postId);
        $postModels = $this->postRepositoryFactory->newForumPosts();

        $this->stopwatch->start('fillWithThreadsFields');
        /** @var Collection<int, int> $parentThreadsID parent tid of all replies and their sub replies */
        $parentThreadsID = $result->replies
            ->map(fn(ReplyKey $postKey) => $postKey->parentPostId)
            ->concat($result->subReplies->map(fn(SubReplyKey $postKey) => $postKey->tid))
            ->unique();
        $this->threads = collect($postModels['thread']->getPosts($parentThreadsID->concat($tids)))
            ->map(fn(\App\Entity\Post\Thread $entity) => Utils::copyClass($entity, Thread::class))
            ->each(static fn(Thread $thread) => // prevent early exit of `Collection::each()` due to the assignment return false
                ($thread->isMatchQuery = $tids->contains($thread->tid)) || true);
        $this->stopwatch->stop('fillWithThreadsFields');

        $this->stopwatch->start('fillWithRepliesFields');
        /** @var Collection<int, int> $parentRepliesID parent pid of all sub replies */
        $parentRepliesID = $result->subReplies->map(fn(SubReplyKey $postKey) => $postKey->parentPostId)->unique();
        $allRepliesId = $parentRepliesID->concat($pids);
        $this->replies = collect($postModels['reply']->getPosts($allRepliesId))
            ->map(fn(\App\Entity\Post\Reply $entity) => Utils::copyClass($entity, Reply::class))
            ->each(static fn(Reply $reply) => // prevent early exit of `Collection::each()` due to the assignment return false
                ($reply->isMatchQuery = $pids->contains($reply->pid)) || true);
        $this->stopwatch->stop('fillWithRepliesFields');

        $this->stopwatch->start('fillWithSubRepliesFields');
        $this->subReplies = collect($postModels['subReply']->getPosts($spids))
            ->map(fn(\App\Entity\Post\SubReply $entity) => Utils::copyClass($entity, SubReply::class));
        $this->stopwatch->stop('fillWithSubRepliesFields');

        $this->stopwatch->start('parsePostContentProtoBufBytes');
        // not using one-to-one association due to relying on PostRepository->getTableNameSuffix()
        $replyContents = collect($this->replyContentRepository->getPostsContent($allRepliesId))
            ->mapWithKeys(fn(ReplyContent $content) => [$content->pid => $content->content]);
        $this->replies->each(fn(Reply $reply) =>
            $reply->content = $replyContents->get($reply->pid));

        $subReplyContents = collect($this->subReplyContentRepository->getPostsContent($spids))
            ->mapWithKeys(fn(SubReplyContent $content) => [$content->spid => $content->content]);
        $this->subReplies->each(fn(SubReply $subReply) =>
            $subReply->content = $subReplyContents->get($subReply->spid));
        $this->stopwatch->stop('parsePostContentProtoBufBytes');

        return [
            'matchQueryPostCount' => collect(Utils::POST_TYPES)
                ->combine([$tids, $pids, $spids])
                ->map(static fn(Collection $ids, string $type) => $ids->count())
                ->toArray(),
            'notMatchQueryParentPostCount' => [
                'thread' => $parentThreadsID->diff($tids)->count(),
                'reply' => $parentRepliesID->diff($pids)->count(),
            ],
        ];
    }

    /** @return Collection<int, Thread> */
    public function nestPostsWithParent(): Collection
    {
        $replies = $this->replies->groupBy(fn(Reply $reply) => $reply->tid);
        $subReplies = $this->subReplies->groupBy(fn(SubReply $subReply) => $subReply->pid);
        return $this->threads->each(fn(Thread $thread) =>
            $thread->replies = $replies
                ->get($thread->tid, collect())
                ->each(fn(Reply $reply) =>
                    $reply->subReplies = $subReplies->get($reply->pid, collect()))
        );
    }

    /**
     * @param Collection<int, Thread> $nestedPosts
     * @return Collection<int, Thread>
     */
    public function reOrderNestedPosts(
        Collection $nestedPosts,
        string $orderByField,
        bool $isOrderByDesc,
    ): Collection {
        $sortBySortingKey = static fn(Collection $posts): Collection => $posts
            ->sortBy(fn(SortablePost $post) => $post->sortingKey, descending: $isOrderByDesc)
            ->values(); // reset keys
        return $sortBySortingKey($nestedPosts->map(
            function (Thread $thread) use ($orderByField, $isOrderByDesc, $sortBySortingKey): Thread {
                $thread->replies = $sortBySortingKey($thread->replies->map(
                    function (Reply $reply) use ($orderByField, $isOrderByDesc): Reply {
                        $reply->subReplies = $reply->subReplies->sortBy(
                            fn(SubReply $subReplies) => $subReplies->$orderByField,
                            descending: $isOrderByDesc,
                        )->values(); // reset keys
                        return $this->setSortingKeyForSortablePost($reply, $reply->subReplies, $orderByField, $isOrderByDesc);
                    }
                ));
                $this->setSortingKeyForSortablePost($thread, $thread->replies, $orderByField, $isOrderByDesc);
                return $thread;
            },
        ));
    }

    /**
     * @template T of Thread|Reply
     * @param T $currentPost
     * @param Collection<(T is Thread ? Reply : (T is Reply ? SubReply : never))> $subPosts
     * @return T
     */
    private function setSortingKeyForSortablePost(
        SortablePost $currentPost,
        Collection $subPosts,
        string $orderByField,
        bool $isOrderByDesc,
    ): SortablePost {
        // use the topmost value between sorting key or value of orderBy field within its sub-posts
        /* @var ?(T is Thread ? Reply : (T is Reply ? SubReply : never)) $firstSubPost */
        $firstSubPost = $subPosts->first();
        $currentAndSubPostSortingKeys = collect([
            // value of orderBy field in the first sorted sub-post that isMatchQuery after previous sorting
            $subPosts // sub replies won't have isMatchQuery
                ->filter(static fn(SortablePost $p) => $p->isMatchQuery === true)
                // if no sub-posts matching the query, use null as the sorting key
                ->first()
                ?->$orderByField,
            // sorting key from the first sorted sub-posts
            // not requiring isMatchQuery since a sub-post without isMatchQuery
            // might have its own sub-posts with isMatchQuery
            // and its sortingKey would be selected from its own sub-posts
            $firstSubPost?->sortingKey,
        ]);
        if ($currentPost->isMatchQuery === true) {
            // also try to use the value of orderBy field in the current post
            $currentAndSubPostSortingKeys->push($currentPost->$orderByField);
        }

        // Collection->filter() will remove falsy values like null
        $currentAndSubPostSortingKeys = $currentAndSubPostSortingKeys->filter()->sort();
        $currentPost->sortingKey = $isOrderByDesc
            ? $currentAndSubPostSortingKeys->last()
            : $currentAndSubPostSortingKeys->first();

        return $currentPost;
    }
}
