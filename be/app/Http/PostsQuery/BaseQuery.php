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

    abstract public function query(QueryParams $params): self;

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
    protected function setResult(int $fid, Collection $queries): void
    {
        /**
         * @param Builder $qb
         * @return Builder
         */
        $addOrderByForBuilder = fn (Builder $qb) =>
            $qb->addSelect($this->orderByField)
                ->orderBy($this->orderByField, $this->orderByDesc === true ? 'DESC' : 'ASC');
        /** @var array{callback: \Closure(PostModel): mixed, descending: bool}|null $resultSortByParams */
        $resultSortByParams = [
            'callback' => fn (PostModel $i) => $i->getAttribute($this->orderByField),
            'descending' => $this->orderByDesc
        ];

        /** @var Collection<string, CursorPaginator> $paginators key by post type */
        $paginators = $queries->map($addOrderByForBuilder)->map(fn (Builder $qb) => $qb->cursorPaginate($this->perPageItems));
        /** @var Collection<string, Collection> $postKeyByTypePluralName */
        $postKeyByTypePluralName = $paginators
            ->flatMap(static fn (CursorPaginator $paginator) => $paginator->collect()) // cast queried posts to Collection<PostModel> then flatten all types of posts
            ->sortBy(...$resultSortByParams) // sort by the required sorting field and direction
            ->take($this->perPageItems) // LIMIT $perPageItems
            ->groupBy(static fn (PostModel $p) => PostModel::POST_CLASS_TO_PLURAL_NAME[$p::class]); // gather limited posts by their type

        Helper::abortAPIIf(40401, $postKeyByTypePluralName->every(static fn (Collection $i) => $i->isEmpty()));
        $this->queryResult = ['fid' => $fid, ...$postKeyByTypePluralName];
        $this->queryResultPages = [
            'nextCursor' => $this->serializeNextPageCursor($postKeyByTypePluralName),
            'hasMorePages' => self::unionPageStats($paginators, 'hasMorePages',
                static fn (Collection $v) => $v->filter()->count() !== 0) // Collection->filter() will remove false values
        ];
    }

    private function serializeNextPageCursor(Collection $postKeyByTypePluralName): string
    {
        return collect(Helper::POST_TYPES_PLURAL)
            // reorder the keys to match the order of Helper::POST_TYPES_PLURAL
            // value of keys that non exists in $postKeyByTypePluralName will remain as int
            ->flip()->replace($postKeyByTypePluralName)
            ->mapWithKeys(static fn (Collection|int $posts, string $type) => [
                array_flip(Helper::POST_TYPE_TO_PLURAL)[$type] =>
                    is_int($posts) ? null : $posts->last() // if $posts is an integer there's no posts of this type (type key not exists in $postKeyByTypePluralName)
            ]) // [singularPostTypeName => lastPostInResult]
            ->map(fn (?PostModel $post, string $typePluralName) => [ // [postID, orderByField]
                $post?->getAttribute(Helper::POST_TYPE_TO_ID[$typePluralName]),
                $post?->getAttribute($this->orderByField)
            ])
            ->map(static fn (array $cursors) => collect($cursors)
                ->map(static function (?int $cursor): string {
                    if ($cursor === null) {
                        return '';
                    }
                    Helper::abortAPIIf(50002, $cursor < 0); // pack('P') only supports positive integers
                    return str_replace(['+', '/', '='], ['-', '_', ''], // https://en.wikipedia.org/wiki/Base64#URL_applications
                        base64_encode(
                            rtrim( // remove trailing 0x00
                                pack('P', $cursor), // unsigned int64 with little endian byte order
                                "\x00")));
                })
                ->join(','))
            ->join(',');
    }

    /**
     * @param string $nextCursor
     * @return Collection<string, Collection<string, int>>
     */
    private function unserializeNextPageCursor(string $nextCursor): Collection
    {
        return collect(Helper::POST_TYPES)
            ->combine(Str::of($nextCursor)
                ->explode(',')
                ->map(static fn (string $cursorBase64): int =>
                    ((array)(
                        unpack('P',
                            str_pad( // re-add removed trailing 0x00
                                base64_decode(
                                    str_replace(['-', '_'], ['+', '/'], $cursorBase64) // https://en.wikipedia.org/wiki/Base64#URL_applications
                                ), 8, "\x00"))
                    ))[1]) // the returned array of unpack() will starts index from 1
                ->chunk(2) // split six values into three post type pairs
                ->map(static fn (Collection $i) => $i->values())) // reorder keys
            ->mapWithKeys(fn (Collection $cursors, string $postType) =>
                [$postType =>
                    $cursors->mapWithKeys(fn (int $cursor, int $index) =>
                        [$index === 0 ? Helper::POST_TYPE_TO_ID[$postType] : $this->orderByField => $cursor])
                ]);
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
