<?php

namespace App\Http\PostsQuery;

use App\Helper;
use App\Tieba\Eloquent\PostModel;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\Eloquent\ReplyModel;
use App\Tieba\Eloquent\SubReplyModel;
use App\Tieba\Eloquent\ThreadModel;
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

    public function __construct(protected int $perPageItems)
    {
    }

    public function getResultPages(): array
    {
        return $this->queryResultPages;
    }

    /**
     * @param int $fid
     * @param Collection<string, Builder> $queries key by post type
     * @return void
     */
    protected function setResult(int $fid, Collection $queries, ?string $cursorParamValue): void
    {
        /**
         * @param Builder $qb
         * @return Builder
         */
        $addOrderByForBuilder = fn (Builder $qb, string $postType) =>
            $qb->addSelect($this->orderByField)
                ->orderBy($this->orderByField, $this->orderByDesc === true ? 'DESC' : 'ASC')
                // cursor paginator requires values of orderBy column are unique
                // if not it should fall back to other unique field (here is the post id primary key)
                // we don't have to select the post id since it's already selected by invokes of PostModel::scopeSelectCurrentAndParentPostID()
                ->orderBy(Helper::POST_TYPE_TO_ID[$postType]);
        /** @var array{callback: \Closure(PostModel): mixed, descending: bool}|null $resultSortByParams */
        $resultSortByParams = [
            'callback' => fn (PostModel $i) => $i->getAttribute($this->orderByField),
            'descending' => $this->orderByDesc
        ];

        if ($cursorParamValue !== null) {
            $cursorKeyByPostType = $this->decodePageCursor($cursorParamValue);
        }
        /** @var Collection<string, CursorPaginator> $paginators key by post type */
        $paginators = $queries->map($addOrderByForBuilder)
            ->map(fn (Builder $qb, string $type) => $qb->cursorPaginate($this->perPageItems, cursor: $cursorKeyByPostType[$type] ?? null));
        /** @var Collection<string, Collection> $postKeyByTypePluralName */
        $postKeyByTypePluralName = $paginators
            ->flatMap(static fn (CursorPaginator $paginator) => $paginator->collect()) // cast queried posts to Collection<PostModel> then flatten all types of posts
            ->sortBy(...$resultSortByParams) // sort by the required sorting field and direction
            ->take($this->perPageItems) // LIMIT $perPageItems
            ->groupBy(static fn (PostModel $p) => PostModel::POST_CLASS_TO_PLURAL_NAME[$p::class]); // gather limited posts by their type

        Helper::abortAPIIf(40401, $postKeyByTypePluralName->every(static fn (Collection $i) => $i->isEmpty()));
        $this->queryResult = ['fid' => $fid, ...$postKeyByTypePluralName];
        $this->queryResultPages = [
            'nextCursor' => $this->encodePageCursor($postKeyByTypePluralName),
            'hasMorePages' => self::unionPageStats($paginators, 'hasMorePages',
                static fn (Collection $v) => $v->filter()->count() !== 0) // Collection->filter() will remove false values
        ];
    }

    /**
     * @param Collection<string, PostModel> $postKeyByTypePluralName
     * @return string
     * @test-input collect(['threads' => collect([new ThreadModel(['tid' => 1,'postTime' => null])]),'replies' => collect([new ReplyModel(['pid' => 2,'postTime' => -2147483649])]),'subReplies' => collect([new SubReplyModel(['spid' => 3,'postTime' => 'test'])])])
     */
    private function encodePageCursor(Collection $postKeyByTypePluralName): string
    {
        $encodedCursorKeyByPostType = $postKeyByTypePluralName
            ->mapWithKeys(static fn (Collection $posts, string $type) => [
                Helper::POST_TYPE_PLURAL_TO_TYPE[$type] => $posts->last()
            ]) // [singularPostTypeName => lastPostInResult]
            ->map(fn (PostModel $post, string $typePluralName) => [ // [postID, orderByField]
                $post->getAttribute(Helper::POST_TYPE_TO_ID[$typePluralName]),
                $post->getAttribute($this->orderByField)
            ])
            ->map(static fn (array $cursors) => collect($cursors)
                ->map(static function (int|string|null $cursor): string {
                    if ($cursor === null) {
                        return 'N'; // a single 'N' is not an valid encoded base64
                        // so it won't get confused with a valid encoded base64, which value is packed with the default format 'P'
                    }
                    $firstKeyFromFilteredTable = static fn (array $table, string $default): string =>
                        array_keys(array_filter($table, static fn (bool $f) => $f === true))[0] ?? $default;
                    $packFormat = $firstKeyFromFilteredTable([
                        'P' => \is_int($cursor) && $cursor >= 0, // unsigned int64 with little endian byte order
                        'q' => \is_int($cursor) && $cursor < 0 // signed int64 with machine byte order (on x86 will be little endian)
                    ], '');
                    $prefix = $firstKeyFromFilteredTable([
                        $packFormat => \is_int($cursor),
                        'S' => \is_string($cursor)
                    ], '');
                    $value = \is_int($cursor)
                        // remove trailing 0x00 for unsigned int or 0xFF for signed negative int
                        ? rtrim(pack($packFormat, $cursor), $cursor >= 0 ? "\x00" : "\xFF")
                        : ($prefix === 'S'
                            ? $cursor // keep string as is since encoded string will always longer than the original string
                            : throw new \RuntimeException('Invalid cursor value'));
                    if ($prefix !== 'S') {
                        $value = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($value)); // https://en.wikipedia.org/wiki/Base64#URL_applications
                    }
                    return ($prefix === 'P' ? '' : $prefix . ':') . $value;
                })
                ->join(','));
        return collect(Helper::POST_TYPES)
            // merge cursors into flipped Helper::POST_TYPES with the same post type key
            // value of keys that non exists in $encodedCursorKeyByPostType will remain as int
            ->flip()->merge($encodedCursorKeyByPostType)
            // if the flipped value is an default int key there's no posts of this type (type key not exists in $postKeyByTypePluralName)
            // so we just return an empty ',' as placeholder
            ->map(static fn (string|int $cursor) => \is_int($cursor) ? ',' : $cursor)
            ->join(',');
    }

    /**
     * @param string $encodedCursors
     * @return Collection<string, Cursor>
     */
    private function decodePageCursor(string $encodedCursors): Collection
    {
        return collect(Helper::POST_TYPES)
            ->combine(Str::of($encodedCursors)
                ->explode(',')
                ->map(static function (string $cursorValueWithPrefix): int|string|null {
                    [$prefix, $value] = array_pad(explode(':', $cursorValueWithPrefix), 2, null);
                    if ($value === null && $prefix !== 'N') { // no prefix being provided means it will be the default 'P'
                        $value = $prefix;
                        $prefix = 'P';
                    }
                    return match($prefix) {
                        'N' => null, // null literal
                        'S' => $value, // string literal is not base64 encoded
                        default => ((array)(
                            unpack($prefix,
                                str_pad( // re-add removed trailing 0x00 or 0xFF
                                    base64_decode(
                                        str_replace(['-', '_'], ['+', '/'], $value) // https://en.wikipedia.org/wiki/Base64#URL_applications
                                    ), 8, $prefix === 'P' ? "\x00" : "\xFF"))
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
    private static function unionPageStats(Collection $paginators, string $unionMethodName, Closure $unionCallback): mixed
    {
        // Collection::filter() will remove falsy values
        $unionValues = $paginators->map(static fn (CursorPaginator $p) => $p->$unionMethodName());
        return $unionCallback($unionValues->isEmpty() ? collect(0) : $unionValues); // prevent empty array
    }

    #[ArrayShape([
        'fid' => 'int',
        'parentThreadCount' => 'int',
        'parentReplyCount' => 'int',
        'threads' => 'Collection<ThreadModel>',
        'replies' => 'Collection<ReplyModel>',
        'subReplies' => 'Collection<SubReplyModel>'
    ])] public function fillWithParentPost(): array
    {
        $result = $this->queryResult;
        /** @var array<int> $tids */
        /** @var array<int> $pids */
        /** @var array<int> $spids */
        /** @var Collection<ThreadModel> $threads */
        /** @var Collection<ReplyModel> $replies */
        /** @var Collection<SubReplyModel> $subReplies */
        [[, $tids], [$replies, $pids], [$subReplies, $spids]] = array_map(
            /**
             * @param string $postIDName
             * @return array{0: array<int>, 1: Collection<PostModel>}
             */
            static function (string $postIDName) use ($result): array {
                $postTypePluralName = Helper::POST_ID_TO_TYPE_PLURAL[$postIDName];
                return \array_key_exists($postTypePluralName, $result)
                    ? [$result[$postTypePluralName], $result[$postTypePluralName]->pluck($postIDName)->toArray()]
                    : [collect(), []];
            },
            Helper::POST_ID
        );

        /** @var int $fid */
        $fid = $result['fid'];
        $postModels = PostModelFactory::getPostModelsByFid($fid);

        /** @var Collection<int> $parentThreadsID parent tid of all replies and their sub replies */
        $parentThreadsID = $replies->pluck('tid')->concat($subReplies->pluck('tid'))->unique();
        $threads = $postModels['thread']
            ->tid($parentThreadsID->concat($tids)) // from the original $this->queryResult, see PostModel::scopeSelectCurrentAndParentPostID()
            ->hidePrivateFields()->get()
            ->map(static fn (ThreadModel $t) => // mark threads that in the original $this->queryResult
                $t->setAttribute('isQueryMatch', \in_array($t->tid, $tids, true)));

        /** @var Collection<int> $parentRepliesID parent pid of all sub replies */
        $parentRepliesID = $subReplies->pluck('pid')->unique();
        $replies = $postModels['reply']
            ->pid($parentRepliesID->concat($pids)) // from the original $this->queryResult, see PostModel::scopeSelectCurrentAndParentPostID()
            ->hidePrivateFields()->get()
            ->map(static fn (ReplyModel $r) => // mark replies that in the original $this->queryResult
                $r->setAttribute('isQueryMatch', \in_array($r->pid, $pids, true)));

        $subReplies = $postModels['subReply']->spid($spids)->hidePrivateFields()->get();

        self::fillPostsContent($fid, $replies, $subReplies);
        return [
            'fid' => $fid,
            'queryMatchCount' => array_sum(array_map('\count', [$tids, $pids, $spids])),
            'parentThreadCount' => $parentThreadsID->diff($tids)->count(),
            'parentReplyCount' => $parentRepliesID->diff($pids)->count(),
            ...array_combine(Helper::POST_TYPES_PLURAL, [$threads, $replies, $subReplies])
        ];
    }

    private static function fillPostsContent(int $fid, Collection $replies, Collection $subReplies)
    {
        $parseThenRenderContentModel = static function (Model $contentModel) {
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
         * @return \Closure
         */
        $appendParsedContent = static fn (Collection $contents, string $postIDName) =>
            static function (PostModel $post) use ($contents, $postIDName) {
                $post->content = $contents[$post[$postIDName]];
                return $post;
            };
        if ($replies->isNotEmpty()) {
            /** @var Collection<?string> $replyContents */
            $replyContents = PostModelFactory::newReplyContent($fid)
                ->pid($replies->pluck('pid'))->get()->keyBy('pid')->map($parseThenRenderContentModel);
            $replies->transform($appendParsedContent($replyContents, 'pid'));
        }
        if ($subReplies->isNotEmpty()) {
            /** @var Collection<?string> $subReplyContents */
            $subReplyContents = PostModelFactory::newSubReplyContent($fid)
                ->spid($subReplies->pluck('spid'))->get()->keyBy('spid')->map($parseThenRenderContentModel);
            $subReplies->transform($appendParsedContent($subReplyContents, 'spid'));
        }
    }

    public static function nestPostsWithParent(Collection $threads, Collection $replies, Collection $subReplies, ...$_): Collection
    {
        // the useless spread parameter $_ will compatible with the array shape which returned by $this->fillWithParentPost()
        return $threads->map(fn (ThreadModel $thread) => [
            ...$thread->toArray(),
            'replies' => $replies->where('tid', $thread->tid)->values() // remove numeric indexed keys
                ->map(fn (ReplyModel $reply) => [
                    ...$reply->toArray(),
                    'subReplies' => $subReplies
                        ->where('pid', $reply->pid)->values()
                        ->map(static fn (SubReplyModel $m) => $m->toArray())
                ])
        ]);
    }

    /**
     * @param Collection<array<array>> $nestedPosts
     * @return array<array<array>>
     * @test-input [{"postTime":1,"isQueryMatch":true,"replies":[{"postTime":2,"isQueryMatch":true,"subReplies":[{"postTime":30}]},{"postTime":20,"isQueryMatch":false,"subReplies":[{"postTime":3}]},{"postTime":4,"isQueryMatch":false,"subReplies":[{"postTime":5},{"postTime":60}]}]},{"postTime":7,"isQueryMatch":false,"replies":[{"postTime":31,"isQueryMatch":true,"subReplies":[]}]}]
     * @test-output [{"postTime":1,"isQueryMatch":true,"replies":[{"postTime":4,"isQueryMatch":false,"subReplies":[{"postTime":60},{"postTime":5}],"sortingKey":60},{"postTime":2,"isQueryMatch":true,"subReplies":[{"postTime":30}],"sortingKey":30},{"postTime":20,"isQueryMatch":false,"subReplies":[{"postTime":3}],"sortingKey":3}],"sortingKey":60},{"postTime":7,"isQueryMatch":false,"replies":[{"postTime":31,"isQueryMatch":true,"subReplies":[],"sortingKey":31}],"sortingKey":31}]
     */
    public function reOrderNestedPosts(Collection $nestedPosts): array
    {
        $getSortingKeyFromCurrentAndChildPosts = function (array $curPost, string $childPostTypePluralName) {
            /** @var Collection<array> $childPosts sorted child posts */
            $childPosts = $curPost[$childPostTypePluralName];
            $curPost[$childPostTypePluralName] = $childPosts->values()->toArray(); // assign child post back to current post

            /** @var Collection<array> $topmostChildPostInQueryMatch the first child post which is isQueryMatch after previous sorting */
            $topmostChildPostInQueryMatch = $childPosts
                ->filter(static fn (array $p) => ($p['isQueryMatch'] ?? true) === true);  // sub replies won't have isQueryMatch
            // use the topmost value between sorting key or value of orderBy field within its child posts
            $curAndChildSortingKeys = collect([
                // value of orderBy field in the first sorted child post
                // if no child posts matching the query, use null as the sorting key
                $topmostChildPostInQueryMatch->first()[$this->orderByField] ?? null,
                // sorting key from the first sorted child posts
                // not requiring isQueryMatch since a child post without isQueryMatch might have its own child posts with isQueryMatch
                // and its sortingKey would be selected from its own child posts
                $childPosts->first()['sortingKey'] ?? null
            ]);
            if ($curPost['isQueryMatch'] === true) {
                // also try to use the value of orderBy field in current post
                $curAndChildSortingKeys->push($curPost[$this->orderByField]);
            }

            $curAndChildSortingKeys = $curAndChildSortingKeys->filter()->sort(); // Collection->filter() will remove falsy values like null
            $curPost['sortingKey'] = $this->orderByDesc ? $curAndChildSortingKeys->last() : $curAndChildSortingKeys->first();
            return $curPost;
        };
        $sortPosts = fn (Collection $posts) => $posts
            ->sortBy('sortingKey', descending: $this->orderByDesc)
            ->map(static function (array $post) { // remove sorting key from posts after sorting
                unset($post['sortingKey']);
                return $post;
            });
        return $sortPosts($nestedPosts
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
    }
}
