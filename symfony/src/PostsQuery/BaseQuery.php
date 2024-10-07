<?php

namespace App\Http\PostsQuery;

use App\Eloquent\Model\Post\Post;
use App\Eloquent\Model\Post\PostFactory;
use App\Eloquent\Model\Post\Reply;
use App\Eloquent\Model\Post\SubReply;
use App\Eloquent\Model\Post\Thread;
use App\Helper;
use Closure;
use Barryvdh\Debugbar\LaravelDebugbar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

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
        private readonly LaravelDebugbar $debugbar,
        private readonly CursorCodec $cursorCodec,
        private readonly int $perPageItems = 50,
    ) {}

    public function getResultPages(): array
    {
        return $this->queryResultPages;
    }

    /**
     * @param int $fid
     * @param Collection<string, Builder<Post>> $queries key by post type
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
        $this->debugbar->startMeasure('setResult');

        $addOrderByForBuilder = fn(Builder $qb, string $postType): Builder => $qb
            // we don't have to select the post ID
            // since it's already selected by invokes of Post::scopeSelectCurrentAndParentPostID()
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
        $this->debugbar->startMeasure('initPaginators');
        /** @var Collection<string, CursorPaginator> $paginators key by post type */
        $paginators = $queriesWithOrderBy->map(fn(Builder $qb, string $type) =>
            $qb->cursorPaginate($this->perPageItems, cursor: $cursorsKeyByPostType[$type] ?? null));
        $this->debugbar->stopMeasure('initPaginators');

        /** @var Collection<string, Collection> $postsKeyByTypePluralName */
        $postsKeyByTypePluralName = $paginators
            // cast paginator with queried posts to Collection<int, Post>
            ->map(static fn(CursorPaginator $paginator) => $paginator->collect())
            ->mapWithKeys(static fn(Collection $posts, string $type) =>
                [Helper::POST_TYPE_TO_PLURAL[$type] => $posts]);
        Helper::abortAPIIf(40401, $postsKeyByTypePluralName->every(static fn(Collection $i) => $i->isEmpty()));
        $this->queryResult = ['fid' => $fid, ...$postsKeyByTypePluralName];

        $hasMore = self::unionPageStats(
            $paginators,
            'hasMorePages',
            static fn(Collection $v) => $v->filter()->count() !== 0, // Collection->filter() will remove false values
        );
        $this->queryResultPages = [
            'currentCursor' => $cursorParamValue ?? '',
            'nextCursor' => $hasMore
                ? $this->cursorCodec->encodeNextCursor(
                    $queryByPostIDParamName === null
                        ? $postsKeyByTypePluralName
                        : $postsKeyByTypePluralName->except([Helper::POST_ID_TO_TYPE_PLURAL[$queryByPostIDParamName]]),
                    $this->orderByField,
                )
                : null,
        ];

        $this->debugbar->stopMeasure('setResult');
    }

    /**
     * Union builders pagination $unionMethodName data by $unionStatement
     *
     * @param Collection<string, CursorPaginator> $paginators key by post type
     * @param string $unionMethodName
     * @param Closure $unionCallback (Collection): TReturn
     * @template TReturn
     * @return TReturn returned by $unionCallback()
     */
    private static function unionPageStats(
        Collection $paginators,
        string $unionMethodName,
        Closure $unionCallback,
    ): mixed {
        // Collection::filter() will remove falsy values
        $unionValues = $paginators->map(static fn(CursorPaginator $p) => $p->$unionMethodName());
        return $unionCallback($unionValues->isEmpty() ? collect(0) : $unionValues); // prevent empty array
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
        $postModels = PostFactory::getPostModelsByFid($fid);

        $this->debugbar->startMeasure('fillWithThreadsFields');
        /** @var Collection<int, int> $parentThreadsID parent tid of all replies and their sub replies */
        $parentThreadsID = $replies->pluck('tid')->concat($subReplies->pluck('tid'))->unique();
        /** @var Collection<int, Thread> $threads */
        $threads = $postModels['thread']
            // from the original $this->queryResult, see Post::scopeSelectCurrentAndParentPostID()
            ->tid($parentThreadsID->concat($tids))
            ->selectPublicFields()->get()
            ->map(static fn(Thread $thread) => // mark threads that exists in the original $this->queryResult
                $thread->setAttribute('isMatchQuery', $tids->contains($thread->tid)));
        $this->debugbar->stopMeasure('fillWithThreadsFields');

        $this->debugbar->startMeasure('fillWithRepliesFields');
        /** @var Collection<int, int> $parentRepliesID parent pid of all sub replies */
        $parentRepliesID = $subReplies->pluck('pid')->unique();
        $replies = $postModels['reply']
            // from the original $this->queryResult, see Post::scopeSelectCurrentAndParentPostID()
            ->pid($parentRepliesID->concat($pids))
            ->selectPublicFields()->with('contentProtoBuf')->get()
            ->map(static fn(Reply $r) => // mark replies that exists in the original $this->queryResult
                $r->setAttribute('isMatchQuery', $pids->contains($r->pid)));
        $this->debugbar->stopMeasure('fillWithRepliesFields');

        $this->debugbar->startMeasure('fillWithSubRepliesFields');
        $subReplies = $postModels['subReply']->spid($spids)
            ->selectPublicFields()->with('contentProtoBuf')->get();
        $this->debugbar->stopMeasure('fillWithSubRepliesFields');

        $this->debugbar->startMeasure('parsePostContentProtoBufBytes');
        $replies->concat($subReplies)->each(function ($post) {
            $post->content = $post->contentProtoBuf?->protoBufBytes?->value;
            unset($post->contentProtoBuf);
        });
        $this->debugbar->stopMeasure('parsePostContentProtoBufBytes');

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
        $this->debugbar->startMeasure('nestPostsWithParent');

        $replies = $replies->groupBy('tid');
        $subReplies = $subReplies->groupBy('pid');
        $ret = $threads
            ->map(fn(Thread $thread) => [
                ...$thread->toArray(),
                'replies' => $replies
                    ->get($thread->tid, collect())
                    ->map(fn(Reply $reply) => [
                        ...$reply->toArray(),
                        'subReplies' => $subReplies->get($reply->pid, collect()),
                    ]),
            ])
            ->recursive();

        $this->debugbar->stopMeasure('nestPostsWithParent');
        return $ret;
    }

    /**
     * @phpcs:ignore Generic.Files.LineLength.TooLong
     * @param Collection<int, Collection<string, mixed|Collection<int, Collection<string, mixed|Collection<int, Collection<string, mixed>>>>>> $nestedPosts
     * @return list<array<string, mixed|list<array<string, mixed|list<array<string, mixed>>>>>>
     */
    public function reOrderNestedPosts(Collection $nestedPosts, bool $shouldRemoveSortingKey = true): array
    {
        $this->debugbar->startMeasure('reOrderNestedPosts');

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

        $this->debugbar->stopMeasure('reOrderNestedPosts');
        return $ret;
    }
}
