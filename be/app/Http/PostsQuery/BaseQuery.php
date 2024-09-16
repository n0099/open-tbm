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
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\ArrayShape;

abstract class BaseQuery
{
    #[ArrayShape([
        'fid' => 'int',
        'threads' => '?Collection<int, Thread>',
        'replies' => '?Collection<int, Reply>',
        'subReplies' => '?Collection<int, SubReply>',
    ])] protected array $queryResult;

    private array $queryResultPages;

    protected string $orderByField;

    protected bool $orderByDesc;

    abstract public function query(QueryParams $params, ?string $cursor): self;

    public function __construct(
        protected LaravelDebugbar $debugbar,
        protected int $perPageItems = 50,
        // @phpcs:ignore Squiz.Functions.MultiLineFunctionDeclaration.BraceOnSameLine, Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore -- https://github.com/squizlabs/PHP_CodeSniffer/issues/3291
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
            $cursorsKeyByPostType = $this->decodeCursor($cursorParamValue);
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
                ? $this->encodeNextCursor($queryByPostIDParamName === null
                    ? $postsKeyByTypePluralName
                    : $postsKeyByTypePluralName->except([Helper::POST_ID_TO_TYPE_PLURAL[$queryByPostIDParamName]]))
                : null,
        ];

        $this->debugbar->stopMeasure('setResult');
    }

    /**
     * @param Collection<string, Post> $postsKeyByTypePluralName
     */
    public function encodeNextCursor(Collection $postsKeyByTypePluralName): string
    {
        $encodedCursorsKeyByPostType = $postsKeyByTypePluralName
            ->mapWithKeys(static fn(Collection $posts, string $type) => [
                Helper::POST_TYPE_PLURAL_TO_TYPE[$type] => $posts->last(), // null when no posts
            ]) // [singularPostTypeName => lastPostInResult]
            ->filter() // remove post types that have no posts
            ->map(fn(Post $post, string $typePluralName) => [ // [postID, orderByField]
                $post->getAttribute(Helper::POST_TYPE_TO_ID[$typePluralName]),
                $post->getAttribute($this->orderByField),
            ])
            ->map(static fn(array $cursors) => collect($cursors)
                ->map(static function (int|string $cursor): string {
                    if (\is_int($cursor) && $cursor === 0) {
                        // quick exit to keep 0 as is
                        // to prevent packed 0 with the default format 'P' after 0x00 trimming is an empty string
                        // that will be confused with post types without a cursor that is a blank encoded cursor ',,'
                        return '0';
                    }

                    $firstKeyFromTableFilterByTrue = static fn(array $table, string $default): string =>
                        array_keys(array_filter($table, static fn(bool $f) => $f === true))[0] ?? $default;
                    $prefix = $firstKeyFromTableFilterByTrue([
                        '-' => \is_int($cursor) && $cursor < 0,
                        '' => \is_int($cursor),
                        'S' => \is_string($cursor),
                    ], '');

                    $value = \is_int($cursor)
                        // remove trailing 0x00 for an unsigned int or 0xFF for a signed negative int
                        ? rtrim(pack('P', $cursor), $cursor >= 0 ? "\x00" : "\xFF")
                        : ($prefix === 'S'
                            // keep string as is since encoded string will always longer than the original string
                            ? $cursor
                            : throw new \RuntimeException('Invalid cursor value'));
                    if ($prefix !== 'S') {
                        // https://en.wikipedia.org/wiki/Base64#URL_applications
                        $value = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($value));
                    }

                    return $prefix . ($prefix === '' ? '' : ':') . $value;
                })
                ->join(','));
        return collect(Helper::POST_TYPES)
            // merge cursors into flipped Helper::POST_TYPES with the same post type key
            // value of keys that non exists in $encodedCursorsKeyByPostType will remain as int
            ->flip()->merge($encodedCursorsKeyByPostType)
            // if the flipped value is a default int key there's no posts of this type
            // (type key not exists in $postsKeyByTypePluralName)
            // so we just return an empty ',' as placeholder
            ->map(static fn(string|int $cursor) => \is_int($cursor) ? ',' : $cursor)
            ->join(',');
    }

    /**
     * @psalm-return Collection<'reply'|'subReply'|'thread', Cursor>
     */
    private function decodeCursor(string $encodedCursors): Collection
    {
        return collect(Helper::POST_TYPES)
            ->combine(Str::of($encodedCursors)
                ->explode(',')
                ->map(static function (string $encodedCursor): int|string|null {
                    [$prefix, $cursor] = array_pad(explode(':', $encodedCursor), 2, null);
                    if ($cursor === null) { // no prefix being provided means the value of cursor is a positive int
                        $cursor = $prefix;
                        $prefix = '';
                    }
                    return match ($prefix) {
                        null => null, // original encoded cursor is an empty string
                        '0' => 0, // keep 0 as is
                        'S' => $cursor, // string literal is not base64 encoded
                        default => ((array) (
                            unpack(
                                'P',
                                str_pad( // re-add removed trailing 0x00 or 0xFF
                                    base64_decode(
                                        // https://en.wikipedia.org/wiki/Base64#URL_applications
                                        str_replace(['-', '_'], ['+', '/'], $cursor),
                                    ),
                                    8,
                                    $prefix === '-' ? "\xFF" : "\x00",
                                ),
                            )
                        ))[1], // the returned array of unpack() will starts index from 1
                    };
                })
                ->chunk(2) // split six values into three post type pairs
                ->map(static fn(Collection $i) => $i->values())) // reorder keys after chunk
            ->mapWithKeys(fn(Collection $cursors, string $postType) =>
                [$postType =>
                    $cursors->mapWithKeys(fn(int|string|null $cursor, int $index) =>
                        [$index === 0 ? Helper::POST_TYPE_TO_ID[$postType] : $this->orderByField => $cursor]),
                ])
            // filter out cursors with all fields value being null, their encoded cursor is ',,'
            ->reject(static fn(Collection $cursors) =>
                $cursors->every(static fn(int|string|null $cursor) => $cursor === null))
            ->map(static fn(Collection $cursors) => new Cursor($cursors->toArray()));
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

    #[ArrayShape([
        'fid' => 'int',
        'matchQueryPostCount' => 'array{thread: int, reply: int, subReply: int}',
        'notMatchQueryParentPostCount' => 'array{thread: int, reply: int}',
        'threads' => 'Collection<int, Thread>',
        'replies' => 'Collection<int, Reply>',
        'subReplies' => 'Collection<int, SubReply>',
    ])] public function fillWithParentPost(): array
    {
        $result = $this->queryResult;
        /** @var Collection<int, int> $tids */
        /** @var Collection<int, int> $pids */
        /** @var Collection<int, int> $spids */
        /** @var Collection<int, Reply> $replies */
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
        $ret = $threads->map(fn(Thread $thread) => collect([
            ...$thread->toArray(),
            'replies' => $replies->get($thread->tid, collect())
                ->map(fn(Reply $reply) => collect([
                    ...$reply->toArray(),
                    'subReplies' => $subReplies->get($reply->pid, collect())
                        ->map(static fn(SubReply $subReply) => collect($subReply->toArray())),
                ])),
        ]));

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
            ? /**
                 * @psalm-return Collection<array-key, Collection>
                 */
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
