<?php

namespace App\Http\PostsQuery;

use App\Helper;
use App\Tieba\Eloquent\PostModel;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\Eloquent\ReplyModel;
use App\Tieba\Eloquent\SubReplyModel;
use App\Tieba\Eloquent\ThreadModel;
use Barryvdh\Debugbar\Facades\Debugbar;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
        'threads' => '?Collection<ThreadModel>',
        'replies' => '?Collection<ReplyModel>',
        'subReplies' => '?Collection<SubReplyModel>',
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
     * @param Collection<string, Builder> $queries key by post type
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
            ->addSelect($this->orderByField)
            ->orderBy($this->orderByField, $this->orderByDesc === true ? 'DESC' : 'ASC')
            // cursor paginator requires values of orderBy column are unique
            // if not it should fall back to other unique field (here is the post ID primary key)
            // we don't have to select the post ID
            // since it's already selected by invokes of PostModel::scopeSelectCurrentAndParentPostID()
            ->orderBy(Helper::POST_TYPE_TO_ID[$postType]);

        $queriesWithOrderBy = $queries->map($addOrderByForBuilder);
        if ($cursorParamValue !== null) {
            $cursorKeyByPostType = $this->decodePageCursor($cursorParamValue);
            // remove queries for post types with encoded cursor ',,'
            $queriesWithOrderBy = $queriesWithOrderBy->intersectByKeys($cursorKeyByPostType);
        }
        Debugbar::startMeasure('initPaginators');
        /** @var Collection<string, CursorPaginator> $paginators key by post type */
        $paginators = $queriesWithOrderBy->map(fn (Builder $qb, string $type) =>
            $qb->cursorPaginate($this->perPageItems, cursor: $cursorKeyByPostType[$type] ?? null));
        Debugbar::stopMeasure('initPaginators');
        /** @var Collection<string, Collection> $postKeyByTypePluralName */
        $postKeyByTypePluralName = $paginators
            // cast paginator with queried posts to Collection<PostModel>
            ->map(static fn (CursorPaginator $paginator) => $paginator->collect())
            ->mapWithKeys(static fn (Collection $posts, string $type) =>
                [Helper::POST_TYPE_TO_PLURAL[$type] => $posts]);

        Helper::abortAPIIf(40401, $postKeyByTypePluralName->every(static fn (Collection $i) => $i->isEmpty()));
        $this->queryResult = ['fid' => $fid, ...$postKeyByTypePluralName];
        $this->queryResultPages = [
            'nextCursor' => $this->encodeNextPageCursor($queryByPostIDParamName === null
                ? $postKeyByTypePluralName
                : $postKeyByTypePluralName->except([Helper::POST_ID_TO_TYPE_PLURAL[$queryByPostIDParamName]])),
            'hasMorePages' => self::unionPageStats(
                $paginators,
                'hasMorePages',
                static fn (Collection $v) => $v->filter()->count() !== 0
            ) // Collection->filter() will remove false values
        ];

        Debugbar::stopMeasure('setResult');
    }

    /**
     * @param Collection<string, PostModel> $postKeyByTypePluralName
     * @return string
     * @test-input collect([
     *     'threads' => collect([new ThreadModel(['tid' => 1,'postTime' => 0])]),
     *     'replies' => collect([new ReplyModel(['pid' => 2,'postTime' => -2147483649])]),
     *     'subReplies' => collect([new SubReplyModel(['spid' => 3,'postTime' => 'test'])])
     * ])
     */
    private function encodeNextPageCursor(Collection $postKeyByTypePluralName): string
    {
        $encodedCursorKeyByPostType = $postKeyByTypePluralName
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
            // value of keys that non exists in $encodedCursorKeyByPostType will remain as int
            ->flip()->merge($encodedCursorKeyByPostType)
            // if the flipped value is a default int key there's no posts of this type
            // (type key not exists in $postKeyByTypePluralName)
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
     * @param Collection<CursorPaginator> $paginators
     * @param string $unionMethodName
     * @param Closure $unionCallback (Collection)
     * @return mixed returned by $unionCallback()
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
        'postsQueryMatchCount' => 'array{thread: int, reply: int, subReply: int}',
        'notMatchQueryParentPostsCount' => 'array{thread: int, reply: int}',
        'threads' => 'Collection<ThreadModel>',
        'replies' => 'Collection<ReplyModel>',
        'subReplies' => 'Collection<SubReplyModel>'
    ])] public function fillWithParentPost(): array
    {
        $result = $this->queryResult;
        /** @var Collection<int> $tids */
        /** @var Collection<int> $pids */
        /** @var Collection<int> $spids */
        /** @var Collection<array-key, ReplyModel> $replies */
        /** @var Collection<array-key, SubReplyModel> $subReplies */
        [[, $tids], [$replies, $pids], [$subReplies, $spids]] = array_map(
            /**
             * @param string $postIDName
             * @return array{0: Collection<PostModel>, 1: Collection<int>}
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
        /** @var Collection<int> $parentThreadsID parent tid of all replies and their sub replies */
        $parentThreadsID = $replies->pluck('tid')->concat($subReplies->pluck('tid'))->unique();
        /** @var Collection<array-key, ThreadModel> $threads */
        $threads = $postModels['thread']
            // from the original $this->queryResult, see PostModel::scopeSelectCurrentAndParentPostID()
            ->tid($parentThreadsID->concat($tids))
            ->hidePrivateFields()->get()
            ->map(static fn (ThreadModel $t) => // mark threads that in the original $this->queryResult
                $t->setAttribute('isQueryMatch', $tids->contains($t->tid)));
        Debugbar::stopMeasure('fillWithThreadsFields');

        Debugbar::startMeasure('fillWithRepliesFields');
        /** @var Collection<int> $parentRepliesID parent pid of all sub replies */
        $parentRepliesID = $subReplies->pluck('pid')->unique();
        $replies = $postModels['reply']
            // from the original $this->queryResult, see PostModel::scopeSelectCurrentAndParentPostID()
            ->pid($parentRepliesID->concat($pids))
            ->hidePrivateFields()->get()
            ->map(static fn (ReplyModel $r) => // mark replies that in the original $this->queryResult
                $r->setAttribute('isQueryMatch', $pids->contains($r->pid)));

        $subReplies = $postModels['subReply']->spid($spids)->hidePrivateFields()->get();
        Debugbar::stopMeasure('fillWithRepliesFields');

        self::fillPostsContent($fid, $replies, $subReplies);
        return [
            'fid' => $fid,
            'postsQueryMatchCount' => collect(Helper::POST_TYPES)
                ->combine([$tids, $pids, $spids])
                ->map(static fn (Collection $ids, string $type) => $ids->count()),
            'notMatchQueryParentPostsCount' => [
                'thread' => $parentThreadsID->diff($tids)->count(),
                'reply' => $parentRepliesID->diff($pids)->count(),
            ],
            ...array_combine(Helper::POST_TYPES_PLURAL, [$threads, $replies, $subReplies])
        ];
    }

    private static function fillPostsContent(int $fid, Collection $replies, Collection $subReplies): void
    {
        $parseThenRenderContentModel = static function (Model $contentModel): ?string {
            if ($contentModel->content === null) {
                return null;
            }
            $proto = new PostContentWrapper();
            $proto->mergeFromString($contentModel->content);
            return str_replace("\n", '', trim(view('renderPostContent', ['content' => $proto->getValue()])->render()));
        };
        /**
         * @param Collection<?string> $contents
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
                        ->pid($replies->pluck('pid'))->get()
                        ->keyBy('pid')->map($parseThenRenderContentModel),
                    'pid'
                )));
        }
        if ($subReplies->isNotEmpty()) {
            Debugbar::measure('fillSubRepliesContent', static fn () =>
            $subReplies->transform($appendParsedContent(
                PostModelFactory::newSubReplyContent($fid)
                    ->spid($subReplies->pluck('spid'))->get()
                    ->keyBy('spid')->map($parseThenRenderContentModel),
                'spid'
            )));
        }
    }

    public static function nestPostsWithParent(
        Collection $threads,
        Collection $replies,
        Collection $subReplies,
        ...$_
    ): Collection {
        Debugbar::startMeasure('nestPostsWithParent');

        $replies = $replies->groupBy('tid');
        $subReplies = $subReplies->groupBy('pid');
        $ret = $threads->map(fn (ThreadModel $thread) => [
            ...$thread->toArray(),
            'replies' => $replies->get($thread->tid, collect())
                ->map(fn (ReplyModel $reply) => [
                    ...$reply->toArray(),
                    'subReplies' => $subReplies->get($reply->pid, collect())
                        ->map(static fn (SubReplyModel $subReply) => $subReply->toArray())
                ])
        ]);

        Debugbar::stopMeasure('nestPostsWithParent');
        return $ret;
    }

    /**
     * @param Collection<array<array>> $nestedPosts
     * @return array<array<array>>
     * @test-input [{"postTime":1,"isQueryMatch":true,"replies":[{"postTime":2,"isQueryMatch":true,"subReplies":[{"postTime":30}]},{"postTime":20,"isQueryMatch":false,"subReplies":[{"postTime":3}]},{"postTime":4,"isQueryMatch":false,"subReplies":[{"postTime":5},{"postTime":60}]}]},{"postTime":7,"isQueryMatch":false,"replies":[{"postTime":31,"isQueryMatch":true,"subReplies":[]}]}]
     * @test-output [{"postTime":1,"isQueryMatch":true,"replies":[{"postTime":4,"isQueryMatch":false,"subReplies":[{"postTime":60},{"postTime":5}],"sortingKey":60},{"postTime":2,"isQueryMatch":true,"subReplies":[{"postTime":30}],"sortingKey":30},{"postTime":20,"isQueryMatch":false,"subReplies":[{"postTime":3}],"sortingKey":3}],"sortingKey":60},{"postTime":7,"isQueryMatch":false,"replies":[{"postTime":31,"isQueryMatch":true,"subReplies":[],"sortingKey":31}],"sortingKey":31}]
     */
    public function reOrderNestedPosts(Collection $nestedPosts): array
    {
        Debugbar::startMeasure('reOrderNestedPosts');

        $getSortingKeyFromCurrentAndChildPosts = function (array $curPost, string $childPostTypePluralName) {
            /** @var Collection<array> $childPosts sorted child posts */
            $childPosts = $curPost[$childPostTypePluralName];
            // assign child post back to current post
            $curPost[$childPostTypePluralName] = $childPosts->values()->toArray();

            // the first child post which is isQueryMatch after previous sorting
            $topmostChildPostInQueryMatch = $childPosts
                // sub replies won't have isQueryMatch
                ->filter(static fn (array $p) => ($p['isQueryMatch'] ?? true) === true);
            // use the topmost value between sorting key or value of orderBy field within its child posts
            $curAndChildSortingKeys = collect([
                // value of orderBy field in the first sorted child post
                // if no child posts matching the query, use null as the sorting key
                $topmostChildPostInQueryMatch->first()[$this->orderByField] ?? null,
                // sorting key from the first sorted child posts
                // not requiring isQueryMatch since a child post without isQueryMatch
                // might have its own child posts with isQueryMatch
                // and its sortingKey would be selected from its own child posts
                $childPosts->first()['sortingKey'] ?? null
            ]);
            if ($curPost['isQueryMatch'] === true) {
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
        $sortPosts = fn (Collection $posts) => $posts
            ->sortBy('sortingKey', descending: $this->orderByDesc)
            ->map(static function (array $post) {
                // remove sorting key from posts after sorting
                unset($post['sortingKey']);
                return $post;
            });
        $ret = $sortPosts(
            $nestedPosts
            ->map(function (array $thread) use ($sortPosts, $getSortingKeyFromCurrentAndChildPosts) {
                $thread['replies'] = $sortPosts(collect($thread['replies'])
                    ->map(function (array $reply) use ($getSortingKeyFromCurrentAndChildPosts) {
                        $reply['subReplies'] = collect($reply['subReplies'])->sortBy(
                            fn (array $subReplies) => $subReplies[$this->orderByField],
                            descending: $this->orderByDesc
                        );
                        return $getSortingKeyFromCurrentAndChildPosts($reply, 'subReplies');
                    }));
                return $getSortingKeyFromCurrentAndChildPosts($thread, 'replies');
            })
        )->values()->toArray();

        Debugbar::stopMeasure('reOrderNestedPosts');
        return $ret;
    }
}
