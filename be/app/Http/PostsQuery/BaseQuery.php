<?php

namespace App\Http\PostsQuery;

use App\Eloquent\Model\Post\Content\PostContentModel;
use App\Eloquent\Model\Post\PostModel;
use App\Eloquent\Model\Post\PostModelFactory;
use App\Eloquent\Model\Post\ReplyModel;
use App\Eloquent\Model\Post\SubReplyModel;
use App\Eloquent\Model\Post\ThreadModel;
use App\Helper;
use Barryvdh\Debugbar\Facades\Debugbar;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\ArrayShape;
use TbClient\Wrapper\PostContentWrapper;

abstract class BaseQuery
{
    #[ArrayShape([
        'fid' => 'int',
        'threads' => '?Collection<int, ThreadModel>',
        'replies' => '?Collection<int, ReplyModel>',
        'subReplies' => '?Collection<int, SubReplyModel>',
    ])] protected array $queryResult;

    private array $queryResultPages;

    protected string $orderByField;

    protected bool $orderByDesc;

    abstract public function query(QueryParams $params, ?string $cursor): self;

    public function __construct(protected int $perPageItems = 200)
    {
    }

    public function getResultPages(): array
    {
        return $this->queryResultPages;
    }

    /**
     * @param int $fid
     * @param Collection<string, Builder<PostModel>> $queries key by post type
     * @param string|null $cursorParamValue
     * @param string|null $queryByPostIDParamName
     * @return void
     */
    protected function setResult(
        int $fid,
        Collection $queries,
        ?string $cursorParamValue,
        ?string $queryByPostIDParamName = null
    ): void {
        Debugbar::startMeasure('setResult');

        $addOrderByForBuilder = fn (Builder $qb, string $postType): Builder => $qb
            // we don't have to select the post ID
            // since it's already selected by invokes of PostModel::scopeSelectCurrentAndParentPostID()
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
            $cursorsKeyByPostType = $this->decodePageCursor($cursorParamValue);
            // remove queries for post types with encoded cursor ',,'
            $queriesWithOrderBy = $queriesWithOrderBy->intersectByKeys($cursorsKeyByPostType);
        }
        Debugbar::startMeasure('initPaginators');
        /** @var Collection<string, CursorPaginator> $paginators key by post type */
        $paginators = $queriesWithOrderBy->map(fn (Builder $qb, string $type) =>
            $qb->cursorPaginate($this->perPageItems, cursor: $cursorsKeyByPostType[$type] ?? null));
        Debugbar::stopMeasure('initPaginators');

        /** @var Collection<string, Collection> $postsKeyByTypePluralName */
        $postsKeyByTypePluralName = $paginators
            // cast paginator with queried posts to Collection<int, PostModel>
            ->map(static fn (CursorPaginator $paginator) => $paginator->collect())
            ->mapWithKeys(static fn (Collection $posts, string $type) =>
                [Helper::POST_TYPE_TO_PLURAL[$type] => $posts]);
        Helper::abortAPIIf(40401, $postsKeyByTypePluralName->every(static fn (Collection $i) => $i->isEmpty()));
        $this->queryResult = ['fid' => $fid, ...$postsKeyByTypePluralName];
        $this->queryResultPages = [
            'nextPageCursor' => $this->encodeNextPageCursor($queryByPostIDParamName === null
                ? $postsKeyByTypePluralName
                : $postsKeyByTypePluralName->except([Helper::POST_ID_TO_TYPE_PLURAL[$queryByPostIDParamName]])),
            'hasMorePages' => self::unionPageStats(
                $paginators,
                'hasMorePages',
                static fn (Collection $v) => $v->filter()->count() !== 0
            ) // Collection->filter() will remove false values
        ];

        Debugbar::stopMeasure('setResult');
    }

    /**
     * @param Collection<string, PostModel> $postsKeyByTypePluralName
     * @return string
     * @test-input collect([
     *     'threads' => collect([new ThreadModel(['tid' => 1,'postedAt' => 0])]),
     *     'replies' => collect([new ReplyModel(['pid' => 2,'postedAt' => -2147483649])]),
     *     'subReplies' => collect([new SubReplyModel(['spid' => 3,'postedAt' => 'test'])])
     * ])
     */
    private function encodeNextPageCursor(Collection $postsKeyByTypePluralName): string
    {
        $encodedCursorsKeyByPostType = $postsKeyByTypePluralName
            ->mapWithKeys(static fn (Collection $posts, string $type) => [
                Helper::POST_TYPE_PLURAL_TO_TYPE[$type] => $posts->last() // null when no posts
            ]) // [singularPostTypeName => lastPostInResult]
            ->filter() // remove post types that have no posts
            ->map(fn (PostModel $post, string $typePluralName) => [ // [postID, orderByField]
                $post->getAttribute(Helper::POST_TYPE_TO_ID[$typePluralName]),
                $post->getAttribute($this->orderByField)
            ])
            ->map(static fn (array $cursors) => collect($cursors)
                ->map(static function (int|string $cursor): string {
                    if (\is_int($cursor) && $cursor === 0) {
                        // quick exit to keep 0 as is
                        // to prevent packed 0 with the default format 'P' after 0x00 trimming is an empty string
                        // that will be confused with post types without a cursor that is a blank encoded cursor ',,'
                        return '0';
                    }

                    $firstKeyFromTableFilterByTrue = static fn (array $table, string $default): string =>
                        array_keys(array_filter($table, static fn (bool $f) => $f === true))[0] ?? $default;
                    $prefix = $firstKeyFromTableFilterByTrue([
                        '-' => \is_int($cursor) && $cursor < 0,
                        '' => \is_int($cursor),
                        'S' => \is_string($cursor)
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
            ->map(static fn (string|int $cursor) => \is_int($cursor) ? ',' : $cursor)
            ->join(',');
    }

    /**
     * @psalm-return Collection<'reply'|'subReply'|'thread', Cursor>
     */
    private function decodePageCursor(string $encodedCursors): Collection
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
                        default => ((array)(
                            unpack(
                                'P',
                                str_pad( // re-add removed trailing 0x00 or 0xFF
                                    base64_decode(
                                        // https://en.wikipedia.org/wiki/Base64#URL_applications
                                        str_replace(['-', '_'], ['+', '/'], $cursor)
                                    ),
                                    8,
                                    $prefix === '-' ? "\xFF" : "\x00"
                                )
                            )
                        ))[1] // the returned array of unpack() will starts index from 1
                    };
                })
                ->chunk(2) // split six values into three post type pairs
                ->map(static fn (Collection $i) => $i->values())) // reorder keys after chunk
            ->mapWithKeys(fn (Collection $cursors, string $postType) =>
                [$postType =>
                    $cursors->mapWithKeys(fn (int|string|null $cursor, int $index) =>
                        [$index === 0 ? Helper::POST_TYPE_TO_ID[$postType] : $this->orderByField => $cursor])
                ])
            // filter out cursors with all fields value being null, their encoded cursor is ',,'
            ->reject(static fn (Collection $cursors) =>
                $cursors->every(static fn (int|string|null $cursor) => $cursor === null))
            ->map(static fn (Collection $cursors) => new Cursor($cursors->toArray()));
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
        Closure $unionCallback
    ): mixed {
        // Collection::filter() will remove falsy values
        $unionValues = $paginators->map(static fn (CursorPaginator $p) => $p->$unionMethodName());
        return $unionCallback($unionValues->isEmpty() ? collect(0) : $unionValues); // prevent empty array
    }

    #[ArrayShape([
        'fid' => 'int',
        'matchQueryPostCount' => 'array{thread: int, reply: int, subReply: int}',
        'notMatchQueryParentPostCount' => 'array{thread: int, reply: int}',
        'threads' => 'Collection<int, ThreadModel>',
        'replies' => 'Collection<int, ReplyModel>',
        'subReplies' => 'Collection<int, SubReplyModel>'
    ])] public function fillWithParentPost(): array
    {
        $result = $this->queryResult;
        /** @var Collection<int, int> $tids */
        /** @var Collection<int, int> $pids */
        /** @var Collection<int, int> $spids */
        /** @var Collection<int, ReplyModel> $replies */
        /** @var Collection<int, SubReplyModel> $subReplies */
        [[, $tids], [$replies, $pids], [$subReplies, $spids]] = array_map(
            /**
             * @param string $postIDName
             * @return array{0: Collection<int, PostModel>, 1: Collection<int, int>}
             */
            static function (string $postIDName) use ($result): array {
                $postTypePluralName = Helper::POST_ID_TO_TYPE_PLURAL[$postIDName];
                return \array_key_exists($postTypePluralName, $result)
                    ? [$result[$postTypePluralName], $result[$postTypePluralName]->pluck($postIDName)]
                    : [collect(), collect()];
            },
            Helper::POST_ID
        );

        /** @var int $fid */
        $fid = $result['fid'];
        $postModels = PostModelFactory::getPostModelsByFid($fid);

        Debugbar::startMeasure('fillWithThreadsFields');
        /** @var Collection<int, int> $parentThreadsID parent tid of all replies and their sub replies */
        $parentThreadsID = $replies->pluck('tid')->concat($subReplies->pluck('tid'))->unique();
        /** @var Collection<int, ThreadModel> $threads */
        $threads = $postModels['thread']
            // from the original $this->queryResult, see PostModel::scopeSelectCurrentAndParentPostID()
            ->tid($parentThreadsID->concat($tids))
            ->selectPublicFields()->get()
            ->map(static fn (ThreadModel $thread) => // mark threads that exists in the original $this->queryResult
                $thread->setAttribute('isMatchQuery', $tids->contains($thread->tid)));
        Debugbar::stopMeasure('fillWithThreadsFields');

        Debugbar::startMeasure('fillWithRepliesFields');
        /** @var Collection<int, int> $parentRepliesID parent pid of all sub replies */
        $parentRepliesID = $subReplies->pluck('pid')->unique();
        $replies = $postModels['reply']
            // from the original $this->queryResult, see PostModel::scopeSelectCurrentAndParentPostID()
            ->pid($parentRepliesID->concat($pids))
            ->selectPublicFields()->get()
            ->map(static fn (ReplyModel $r) => // mark replies that exists in the original $this->queryResult
                $r->setAttribute('isMatchQuery', $pids->contains($r->pid)));
        Debugbar::stopMeasure('fillWithRepliesFields');

        Debugbar::startMeasure('fillWithSubRepliesFields');
        $subReplies = $postModels['subReply']->spid($spids)->selectPublicFields()->get();
        Debugbar::stopMeasure('fillWithSubRepliesFields');

        self::fillPostsContent($fid, $replies, $subReplies);
        return [
            'fid' => $fid,
            'matchQueryPostCount' => collect(Helper::POST_TYPES)
                ->combine([$tids, $pids, $spids])
                ->map(static fn (Collection $ids, string $type) => $ids->count()),
            'notMatchQueryParentPostCount' => [
                'thread' => $parentThreadsID->diff($tids)->count(),
                'reply' => $parentRepliesID->diff($pids)->count(),
            ],
            ...array_combine(Helper::POST_TYPES_PLURAL, [$threads, $replies, $subReplies])
        ];
    }

    private static function fillPostsContent(int $fid, Collection $replies, Collection $subReplies): void
    {
        $parseThenRenderContentModel = static function (PostContentModel $contentModel): ?string {
            if ($contentModel->protoBufBytes === null) {
                return null;
            }
            $proto = new PostContentWrapper();
            $proto->mergeFromString($contentModel->protoBufBytes);
            $renderedView = view('renderPostContent', ['content' => $proto->getValue()])->render();
            return str_replace("\n", '', trim($renderedView));
        };
        /**
         * @param Collection<int, ?string> $contents key by post ID
         * @param string $postIDName
         * @return Closure
         * @psalm-return Closure(PostModel):PostModel
         */
        $appendParsedContent = static fn (Collection $contents, string $postIDName): Closure =>
            static function (PostModel $post) use ($contents, $postIDName): PostModel {
                $post->content = $contents[$post[$postIDName]];
                return $post;
            };
        if ($replies->isNotEmpty()) {
            Debugbar::measure('fillRepliesContent', static fn () =>
                $replies->transform($appendParsedContent(
                    PostModelFactory::newReplyContent($fid)
                        ->pid($replies->pluck('pid'))->selectPublicFields()->get()
                        ->keyBy('pid')->map($parseThenRenderContentModel),
                    'pid'
                )));
        }
        if ($subReplies->isNotEmpty()) {
            Debugbar::measure('fillSubRepliesContent', static fn () =>
            $subReplies->transform($appendParsedContent(
                PostModelFactory::newSubReplyContent($fid)
                    ->spid($subReplies->pluck('spid'))->selectPublicFields()->get()
                    ->keyBy('spid')->map($parseThenRenderContentModel),
                'spid'
            )));
        }
    }

    /**
     * @param Collection<int, ThreadModel> $threads
     * @param Collection<int, ReplyModel> $replies
     * @param Collection<int, SubReplyModel> $subReplies
     * @param mixed ...$_
     * @return Collection<int, Collection<string, mixed|Collection<int, Collection<string, mixed|Collection<int, Collection<string, mixed>>>>>>
     */
    public static function nestPostsWithParent(
        Collection $threads,
        Collection $replies,
        Collection $subReplies,
        ...$_
    ): Collection {
        Debugbar::startMeasure('nestPostsWithParent');

        $replies = $replies->groupBy('tid');
        $subReplies = $subReplies->groupBy('pid');
        $ret = $threads->map(fn (ThreadModel $thread) => collect([
            ...$thread->toArray(),
            'replies' => $replies->get($thread->tid, collect())
                ->map(fn (ReplyModel $reply) => collect([
                    ...$reply->toArray(),
                    'subReplies' => $subReplies->get($reply->pid, collect())
                        ->map(static fn (SubReplyModel $subReply) => collect($subReply->toArray()))
                ]))
        ]));

        Debugbar::stopMeasure('nestPostsWithParent');
        return $ret;
    }

    /**
     * @param Collection<int, Collection<string, mixed|Collection<int, Collection<string, mixed|Collection<int, Collection<string, mixed>>>>>> $nestedPosts
     * @return list<array<string, mixed|list<array<string, mixed|list<array<string, mixed>>>>>>
     * @test-input [{"postedAt":1,"isMatchQuery":true,"replies":[{"postedAt":2,"isMatchQuery":true,"subReplies":[{"postedAt":30}]},{"postedAt":20,"isMatchQuery":false,"subReplies":[{"postedAt":3}]},{"postedAt":4,"isMatchQuery":false,"subReplies":[{"postedAt":5},{"postedAt":60}]}]},{"postedAt":7,"isMatchQuery":false,"replies":[{"postedAt":31,"isMatchQuery":true,"subReplies":[]}]}]
     * @test-output [{"postedAt":1,"isMatchQuery":true,"replies":[{"postedAt":4,"isMatchQuery":false,"subReplies":[{"postedAt":60},{"postedAt":5}],"sortingKey":60},{"postedAt":2,"isMatchQuery":true,"subReplies":[{"postedAt":30}],"sortingKey":30},{"postedAt":20,"isMatchQuery":false,"subReplies":[{"postedAt":3}],"sortingKey":3}],"sortingKey":60},{"postedAt":7,"isMatchQuery":false,"replies":[{"postedAt":31,"isMatchQuery":true,"subReplies":[],"sortingKey":31}],"sortingKey":31}]
     */
    public function reOrderNestedPosts(Collection $nestedPosts): array
    {
        Debugbar::startMeasure('reOrderNestedPosts');

        /**
         * @param Collection<int, Collection<string, mixed|Collection<int, Collection<string, mixed>>>> $curPost
         * @param string $childPostTypePluralName
         * @return Collection<int, Collection<string, mixed|Collection<int, Collection<string, mixed>>>>
         */
        $setSortingKeyFromCurrentAndChildPosts = function (Collection $curPost, string $childPostTypePluralName) {
            /** @var Collection<int, Collection<string, mixed>> $childPosts sorted child posts */
            $childPosts = $curPost[$childPostTypePluralName];
            $curPost[$childPostTypePluralName] = $childPosts->values(); // reset keys

            // use the topmost value between sorting key or value of orderBy field within its child posts
            $curAndChildSortingKeys = collect([
                // value of orderBy field in the first sorted child post that isMatchQuery after previous sorting
                $childPosts
                    // sub replies won't have isMatchQuery
                    ->filter(static fn (Collection $p) => ($p['isMatchQuery'] ?? true) === true)
                    // if no child posts matching the query, use null as the sorting key
                    ->first()[$this->orderByField] ?? null,
                // sorting key from the first sorted child posts
                // not requiring isMatchQuery since a child post without isMatchQuery
                // might have its own child posts with isMatchQuery
                // and its sortingKey would be selected from its own child posts
                $childPosts->first()['sortingKey'] ?? null
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
        $sortBySortingKey = fn (Collection $posts) => $posts
            ->sortBy(fn (Collection $i) => $i['sortingKey'], descending: $this->orderByDesc);
        $removeSortingKey = static fn (Collection $posts) => $posts
            ->map(fn (Collection $i) => $i->except('sortingKey'));
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
                                fn (Collection $subReplies) => $subReplies[$this->orderByField],
                                descending: $this->orderByDesc
                            );
                            return $setSortingKeyFromCurrentAndChildPosts($reply, 'subReplies');
                        }
                    ));
                    $setSortingKeyFromCurrentAndChildPosts($thread, 'replies');
                    $thread['replies'] = $removeSortingKey($thread['replies']);
                    return $thread;
                }
            )
        ))->values()->toArray();

        Debugbar::stopMeasure('reOrderNestedPosts');
        return $ret;
    }
}
