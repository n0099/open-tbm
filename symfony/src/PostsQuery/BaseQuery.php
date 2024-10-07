<?php

namespace App\PostsQuery;

use App\Entity\Post\Post;
use App\Entity\Post\Reply;
use App\Entity\Post\SubReply;
use App\Entity\Post\Thread;
use App\Helper;
use App\Repository\Post\PostRepositoryFactory;
use Doctrine\ORM\QueryBuilder;
use Illuminate\Support\Collection;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

abstract class BaseQuery
{
    /** @type array{
     *     fid: int,
     *     threads: ?Collection<int, Thread>,
     *     replies: ?Collection<int, Reply>,
     *     subReplies: ?Collection<int, SubReply>
     *  }
     */
    protected array $queryResult;

    private array $queryResultPages;

    protected string $orderByField;

    protected bool $orderByDesc;

    abstract public function query(QueryParams $params, ?string $cursor): self;

    public function __construct(
        private readonly SerializerInterface $serializer,
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

        $addOrderByForBuilder = fn(QueryBuilder $qb, string $postType): QueryBuilder => $qb
            // we don't have to select the post ID
            // since it's already selected by invokes of PostRepository->selectCurrentAndParentPostID()
            ->addSelect($this->orderByField)
            ->orderBy($this->orderByField, $this->orderByDesc === true ? 'DESC' : 'ASC')
            // cursor paginator requires values of orderBy column are unique
            // if not it should fall back to other unique field (here is the post ID primary key)
            // https://use-the-index-luke.com/no-offset
            // https://mysql.rjweb.org/doc.php/pagination
            // https://medium.com/swlh/how-to-implement-cursor-pagination-like-a-pro-513140b65f32
            // https://slack.engineering/evolving-api-pagination-at-slack/
            ->orderBy(Helper::POST_TYPE_TO_ID[$postType]);

        $queriesWithOrderBy = $queries->map($addOrderByForBuilder);
        $cursorsKeyByPostType = null;
        if ($cursorParamValue !== null) {
            $cursorsKeyByPostType = $this->cursorCodec->decodeCursor($cursorParamValue, $this->orderByField);
            // remove queries for post types with encoded cursor ',,'
            $queriesWithOrderBy = $queriesWithOrderBy->intersectByKeys($cursorsKeyByPostType);
        }
        $this->stopwatch->start('initPaginators');
        /** @var Collection<string, QueryBuilder> $paginators key by post type */
        $paginators = $queriesWithOrderBy->map(function (QueryBuilder $qb, string $type) use ($cursorsKeyByPostType) {
            $cursors = $cursorsKeyByPostType[$type];
            if ($cursors === null) {
                return $qb;
            }
            return collect($cursors)->reduce(
                static fn(QueryBuilder $acc, $cursorValue, string $cursorField): QueryBuilder =>
                    $acc->andWhere("t.$cursorField > :cursorValue")->setParameter('cursorValue', $cursorValue),
                $qb
            )?->setMaxResults($this->perPageItems + 1);
        });
        $this->stopwatch->stop('initPaginators');

        $hasMorePages = false;
        /** @var Collection<string, Collection> $postsKeyByTypePluralName */
        $postsKeyByTypePluralName = $paginators
            ->mapWithKeys(function (QueryBuilder $paginator, string $postType) use (&$hasMorePages) {
                $posts = collect($paginator->getQuery()->getResult());
                if ($posts->count() === $this->perPageItems + 1) {
                    $posts->pop();
                    $hasMorePages = true;
                }
                return [Helper::POST_TYPE_TO_PLURAL[$postType] => $posts];
            });
        Helper::abortAPIIf(40401, $postsKeyByTypePluralName->every(static fn(Collection $i) => $i->isEmpty()));
        $this->queryResult = ['fid' => $fid, ...$postsKeyByTypePluralName];

        $this->queryResultPages = [
            'currentCursor' => $cursorParamValue ?? '',
            'nextCursor' => $hasMorePages
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
        $threads = array_map(
            static fn(Thread $thread) =>
                $thread->setIsMatchQuery($tids->contains($thread->getTid())),
            $postModels['thread']->createQueryBuilder('t')
                ->where('t.tid IN (:tids)')->setParameter('tids', $parentThreadsID->concat($tids)->toArray())
                ->getQuery()->getResult()
        );
        $this->stopwatch->stop('fillWithThreadsFields');

        $this->stopwatch->start('fillWithRepliesFields');
        /** @var Collection<int, int> $parentRepliesID parent pid of all sub replies */
        $parentRepliesID = $subReplies->pluck('pid')->unique();
        $replies = array_map(
            static fn(Reply $reply) =>
                $reply->setIsMatchQuery($pids->contains($reply->getPid())),
            $postModels['reply']->createQueryBuilder('t')
                ->where('t.pid IN (:pids)')->setParameter('pids', $parentRepliesID->concat($pids)->toArray())
                ->getQuery()->getResult()
        );
        $this->stopwatch->stop('fillWithRepliesFields');

        $this->stopwatch->start('fillWithSubRepliesFields');
        $subReplies = $postModels['subReply']->createQueryBuilder('t')
            ->where('t.spid IN (:spids)')->setParameter('spids', $spids->toArray())
            ->getQuery()->getResult();
        $this->stopwatch->stop('fillWithSubRepliesFields');

        /*
        $this->stopwatch->start('parsePostContentProtoBufBytes');
        $replies->concat($subReplies)->each(function ($post) {
            $post->content = $post->contentProtoBuf?->protoBufBytes?->value;
            unset($post->contentProtoBuf);
        });
        $this->stopwatch->stop('parsePostContentProtoBufBytes');
        */

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

        $replies = $replies->groupBy('tid');
        $subReplies = $subReplies->groupBy('pid');
        $ret = $threads
            ->map(fn(Thread $thread) => [
                ...$this->serializer->normalize($thread),
                'replies' => $replies
                    ->get($thread->getTid(), collect())
                    ->map(fn(Reply $reply) => [
                        ...$this->serializer->normalize($reply),
                        'subReplies' => $subReplies->get($reply->getPid(), collect()),
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
                $childPosts
                    // sub replies won't have isMatchQuery
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
                                fn(Collection $subReplies) => $subReplies[$this->orderByField],
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
