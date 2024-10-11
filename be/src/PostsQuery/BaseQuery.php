<?php

namespace App\PostsQuery;

use App\Entity\Post\Content\ReplyContent;
use App\Entity\Post\Content\SubReplyContent;
use App\Entity\Post\Post;
use App\Entity\Post\Reply;
use App\Entity\Post\SubReply;
use App\Entity\Post\Thread;
use App\Helper;
use App\Repository\Post\PostRepositoryFactory;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Illuminate\Support\Collection;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/** @psalm-import-type PostsKeyByTypePluralName from CursorCodec */
abstract class BaseQuery
{
    /** @type array{
     *     fid: int,
     *     threads: ?Collection<int, Thread>,
     *     replies: ?Collection<int, Reply>,
     *     subReplies: ?Collection<int, SubReply>
     *  }
     */
    private array $queryResult;

    private array $queryResultPages;

    protected string $orderByField;

    protected bool $orderByDesc;

    abstract public function query(QueryParams $params, ?string $cursor): void;

    public function __construct(
        private readonly NormalizerInterface $normalizer,
        private readonly Stopwatch $stopwatch,
        private readonly CursorCodec $cursorCodec,
        private readonly PostRepositoryFactory $postRepositoryFactory,
        private readonly int $perPageItems = 50,
    ) {}

    public function getResultPages(): array
    {
        return $this->queryResultPages;
    }

    /**
     * @param int $fid
     * @param Collection<string, QueryBuilder> $queries key by post type
     * @param string|null $cursorParamValue
     * @param string|null $queryByPostIDParamName
     * @return void
     */
    protected function setResult(
        int $fid,
        Collection $queries,
        ?string $cursorParamValue,
        ?string $queryByPostIDParamName = null,
    ): void {
        $this->stopwatch->start('setResult');

        $orderedQueries = $queries->map(fn(QueryBuilder $qb, string $postType): QueryBuilder => $qb
            // we don't have to select the post ID
            // since it's already selected by invokes of PostRepository->selectCurrentAndParentPostID()
            ->addSelect("t.$this->orderByField")
            ->addOrderBy("t.$this->orderByField", $this->orderByDesc === true ? 'DESC' : 'ASC')
            // cursor paginator requires values of orderBy column are unique
            // if not it should fall back to other unique field (here is the post ID primary key)
            // https://use-the-index-luke.com/no-offset
            // https://mysql.rjweb.org/doc.php/pagination
            // https://medium.com/swlh/how-to-implement-cursor-pagination-like-a-pro-513140b65f32
            // https://slack.engineering/evolving-api-pagination-at-slack/
            ->addOrderBy('t.' . Helper::POST_TYPE_TO_ID[$postType]));
        $cursorsKeyByPostType = null;
        if ($cursorParamValue !== null) {
            $cursorsKeyByPostType = $this->cursorCodec->decodeCursor($cursorParamValue, $this->orderByField);
            // remove queries for post types with encoded cursor ',,'
            $orderedQueries = $orderedQueries->intersectByKeys($cursorsKeyByPostType);
        }
        $this->stopwatch->start('initPaginators');
        /** @var Collection<string, QueryBuilder> $paginators key by post type */
        $paginators = $orderedQueries->each(function (QueryBuilder $qb, string $type) use ($cursorsKeyByPostType) {
            $cursors = $cursorsKeyByPostType?->get($type);
            if ($cursors === null) {
                return;
            }
            $cursors = collect($cursors);
            $comparisons = $cursors->keys()->map(
                fn(string $fieldName): Expr\Comparison => $this->orderByDesc
                    ? $qb->expr()->lt("t.$fieldName", ":cursor_$fieldName")
                    : $qb->expr()->gt("t.$fieldName", ":cursor_$fieldName"),
            );
            $qb->andWhere($qb->expr()->orX(...$comparisons));
            $cursors->mapWithKeys(fn($fieldValue, string $fieldName) =>
                $qb->setParameter("cursor_$fieldName", $fieldValue)); // prevent overwriting existing param
        });
        $this->stopwatch->stop('initPaginators');

        $resultsAndHasMorePages = $paginators->map(fn(QueryBuilder $paginator) =>
            self::hasQueryResultMorePages($paginator, $this->perPageItems));
        /** @var PostsKeyByTypePluralName $postsKeyByTypePluralName */
        $postsKeyByTypePluralName = $resultsAndHasMorePages
            ->mapWithKeys(fn(array $resultAndHasMorePages, string $postType) =>
                [Helper::POST_TYPE_TO_PLURAL[$postType] => $resultAndHasMorePages['result']]);
        Helper::abortAPIIf(40401, $postsKeyByTypePluralName->every(static fn(Collection $i) => $i->isEmpty()));
        $this->queryResult = ['fid' => $fid, ...$postsKeyByTypePluralName];

        $this->queryResultPages = [
            'currentCursor' => $cursorParamValue ?? '',
            'nextCursor' => $resultsAndHasMorePages->pluck('hasMorePages')
                    ->filter()->isNotEmpty() // filter() remove falsy
                ? $this->cursorCodec->encodeNextCursor(
                    $queryByPostIDParamName === null
                        ? $postsKeyByTypePluralName
                        : $postsKeyByTypePluralName->except([Helper::POST_ID_TO_TYPE_PLURAL[$queryByPostIDParamName]]),
                    $this->orderByField,
                )
                : null,
        ];

        $this->stopwatch->stop('setResult');
    }

    /** @return array{result: Collection, hasMorePages: bool} */
    public static function hasQueryResultMorePages(QueryBuilder $query, int $limit): array
    {
        $results = collect($query->setMaxResults($limit + 1)->getQuery()->getResult());
        if ($results->count() === $limit + 1) {
            $results->pop();
            $hasMorePages = true;
        }
        return ['result' => $results, 'hasMorePages' => $hasMorePages ?? false];
    }

    /**
     * @return array{
     *     fid: int,
     *     threads: Collection<int, Thread>,
     *     replies: Collection<int, Reply>,
     *     subReplies: Collection<int, SubReply>,
     *     matchQueryPostCount: array{thread: int, reply: int, subReply: int},
     *     notMatchQueryParentPostCount: array{thread: int, reply: int},
     * }
     */
    public function fillWithParentPost(): array
    {
        $result = $this->queryResult;
        /** @var Collection<int, int> $tids */
        /** @var Collection<int, int> $pids */
        /** @var Collection<int, int> $spids */
        /** @var Collection<int, Reply> $replies */
        /** @var Collection<int, SubReply> $subReplies */
        [[, $tids], [$replies, $pids], [$subReplies, $spids]] = array_map(
            /**
             * @param string $postIDName
             * @return array{0: Collection<int, Post>, 1: Collection<int, int>}
             */
            static function (string $postIDName) use ($result): array {
                $postTypePluralName = Helper::POST_ID_TO_TYPE_PLURAL[$postIDName];
                return \array_key_exists($postTypePluralName, $result)
                    ? [$result[$postTypePluralName], $result[$postTypePluralName]->pluck($postIDName)]
                    : [collect(), collect()];
            },
            Helper::POST_ID,
        );

        /** @var int $fid */
        $fid = $result['fid'];
        $postModels = $this->postRepositoryFactory->newForumPosts($fid);

        $this->stopwatch->start('fillWithThreadsFields');
        /** @var Collection<int, int> $parentThreadsID parent tid of all replies and their sub replies */
        $parentThreadsID = $replies->pluck('tid')->concat($subReplies->pluck('tid'))->unique();
        /** @var Collection<int, Thread> $threads */
        $threads = collect($postModels['thread']->getPosts($parentThreadsID->concat($tids)))
            ->each(static fn(Thread $thread) =>
                $thread->setIsMatchQuery($tids->contains($thread->getTid())));
        $this->stopwatch->stop('fillWithThreadsFields');

        $this->stopwatch->start('fillWithRepliesFields');
        /** @var Collection<int, int> $parentRepliesID parent pid of all sub replies */
        $parentRepliesID = $subReplies->pluck('pid')->unique();
        $allRepliesId = $parentRepliesID->concat($pids);
        $replies = collect($postModels['reply']->getPosts($allRepliesId))
            ->each(static fn(Reply $reply) =>
                $reply->setIsMatchQuery($pids->contains($reply->getPid())));
        $this->stopwatch->stop('fillWithRepliesFields');

        $this->stopwatch->start('fillWithSubRepliesFields');
        $subReplies = collect($postModels['subReply']->getPosts($spids));
        $this->stopwatch->stop('fillWithSubRepliesFields');

        $this->stopwatch->start('parsePostContentProtoBufBytes');
        // not using one-to-one association due to relying on PostRepository->getTableNameSuffix()
        $replyContents = collect($this->postRepositoryFactory->newReplyContent($fid)->getPostsContent($allRepliesId))
            ->mapWithKeys(fn(ReplyContent $content) => [$content->getPid() => $content->getContent()]);
        $replies->each(fn(Reply $reply) =>
            $reply->setContent($replyContents->get($reply->getPid())));

        $subReplyContents = collect($this->postRepositoryFactory->newSubReplyContent($fid)->getPostsContent($spids))
            ->mapWithKeys(fn(SubReplyContent $content) => [$content->getSpid() => $content->getContent()]);
        $subReplies->each(fn(SubReply $subReply) =>
            $subReply->setContent($subReplyContents->get($subReply->getSpid())));
        $this->stopwatch->stop('parsePostContentProtoBufBytes');

        return [
            'fid' => $fid,
            'matchQueryPostCount' => collect(Helper::POST_TYPES)
                ->combine([$tids, $pids, $spids])
                ->map(static fn(Collection $ids, string $type) => $ids->count()),
            'notMatchQueryParentPostCount' => [
                'thread' => $parentThreadsID->diff($tids)->count(),
                'reply' => $parentRepliesID->diff($pids)->count(),
            ],
            ...array_combine(Helper::POST_TYPES_PLURAL, [$threads, $replies, $subReplies]),
        ];
    }

    /**
     * @param Collection<int, Thread> $threads
     * @param Collection<int, Reply> $replies
     * @param Collection<int, SubReply> $subReplies
     * @phpcs:ignore Generic.Files.LineLength.TooLong
     * @return Collection<int, Collection<string, mixed|Collection<int, Collection<string, mixed|Collection<int, Collection<string, mixed>>>>>>
     * @SuppressWarnings(PHPMD.CamelCaseParameterName)
     */
    public function nestPostsWithParent(
        Collection $threads,
        Collection $replies,
        Collection $subReplies,
        ...$_,
    ): Collection {
        $this->stopwatch->start('nestPostsWithParent');

        $replies = $replies->groupBy(fn(Reply $reply) => $reply->getTid());
        $subReplies = $subReplies->groupBy(fn(SubReply $subReply) => $subReply->getPid());
        $ret = $threads
            ->map(fn(Thread $thread) => [
                ...$this->normalizer->normalize($thread),
                'replies' => $replies
                    ->get($thread->getTid(), collect())
                    ->map(fn(Reply $reply) => [
                        ...$this->normalizer->normalize($reply),
                        'subReplies' => $this->normalizer->normalize($subReplies->get($reply->getPid(), collect())),
                    ]),
            ])
            ->recursive();

        $this->stopwatch->stop('nestPostsWithParent');
        return $ret;
    }

    /**
     * @phpcs:ignore Generic.Files.LineLength.TooLong
     * @param Collection<int, Collection<string, mixed|Collection<int, Collection<string, mixed|Collection<int, Collection<string, mixed>>>>>> $nestedPosts
     * @return list<array<string, mixed|list<array<string, mixed|list<array<string, mixed>>>>>>
     */
    public function reOrderNestedPosts(Collection $nestedPosts, bool $shouldRemoveSortingKey = true): array
    {
        $this->stopwatch->start('reOrderNestedPosts');

        /**
         * @param Collection<int, Collection<string, mixed|Collection<int, Collection<string, mixed>>>> $curPost
         * @param string $childPostTypePluralName
         * @return Collection<int, Collection<string, mixed|Collection<int, Collection<string, mixed>>>>
         */
        $setSortingKeyFromCurrentAndChildPosts = function (
            Collection $curPost,
            string $childPostTypePluralName,
        ): Collection {
            /** @var Collection<int, Collection<string, mixed>> $childPosts sorted child posts */
            $childPosts = $curPost[$childPostTypePluralName];
            $curPost[$childPostTypePluralName] = $childPosts->values(); // reset keys

            // use the topmost value between sorting key or value of orderBy field within its child posts
            $curAndChildSortingKeys = collect([
                // value of orderBy field in the first sorted child post that isMatchQuery after previous sorting
                $childPosts // sub replies won't have isMatchQuery
                    ->filter(static fn(Collection $p) => ($p['isMatchQuery'] ?? true) === true)
                    // if no child posts matching the query, use null as the sorting key
                    ->first()[$this->orderByField] ?? null,
                // sorting key from the first sorted child posts
                // not requiring isMatchQuery since a child post without isMatchQuery
                // might have its own child posts with isMatchQuery
                // and its sortingKey would be selected from its own child posts
                $childPosts->first()['sortingKey'] ?? null,
            ]);
            if ($curPost['isMatchQuery'] === true) {
                // also try to use the value of orderBy field in current post
                $curAndChildSortingKeys->push($curPost[$this->orderByField]);
            }

            // Collection->filter() will remove falsy values like null
            $curAndChildSortingKeys = $curAndChildSortingKeys->filter()->sort();
            $curPost['sortingKey'] = $this->orderByDesc
                ? $curAndChildSortingKeys->last()
                : $curAndChildSortingKeys->first();

            return $curPost;
        };
        $sortBySortingKey = fn(Collection $posts): Collection => $posts
            ->sortBy(fn(Collection $i) => $i['sortingKey'], descending: $this->orderByDesc);
        $removeSortingKey = $shouldRemoveSortingKey
            ? /** @psalm-return Collection<array-key, Collection> */
                static fn(Collection $posts): Collection => $posts
                    ->map(fn(Collection $i) => $i->except('sortingKey'))
            : static fn($i) => $i;
        $ret = $removeSortingKey($sortBySortingKey(
            $nestedPosts->map(
                /**
                 * @param Collection{replies: Collection} $thread
                 * @return Collection{replies: Collection}
                 */
                function (Collection $thread) use (
                    $sortBySortingKey,
                    $removeSortingKey,
                    $setSortingKeyFromCurrentAndChildPosts
                ) {
                    $thread['replies'] = $sortBySortingKey($thread['replies']->map(
                        /**
                         * @param Collection{subReplies: Collection} $reply
                         * @return Collection{subReplies: Collection}
                         */
                        function (Collection $reply) use ($setSortingKeyFromCurrentAndChildPosts) {
                            $reply['subReplies'] = $reply['subReplies']->sortBy(
                                fn(Collection $subReplies) => $subReplies->get($this->orderByField),
                                descending: $this->orderByDesc,
                            );
                            return $setSortingKeyFromCurrentAndChildPosts($reply, 'subReplies');
                        },
                    ));
                    $setSortingKeyFromCurrentAndChildPosts($thread, 'replies');
                    $thread['replies'] = $removeSortingKey($thread['replies']);
                    return $thread;
                },
            ),
        ))->values()->toArray();

        $this->stopwatch->stop('reOrderNestedPosts');
        return $ret;
    }
}
