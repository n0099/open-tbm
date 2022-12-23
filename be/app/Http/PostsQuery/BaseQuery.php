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
use JetBrains\PhpStorm\ArrayShape;
use TbClient\Wrapper\PostContentWrapper;

trait BaseQuery
{
    #[ArrayShape([
        'fid' => 'int',
        'threads' => '?Collection<ThreadModel>',
        'replies' => '?Collection<ReplyModel>',
        'subReplies' => '?Collection<SubReplyModel>',
    ])] protected array $queryResult;

    private array $queryResultPages;

    protected string $orderByField;

    protected string $orderByDirection;

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
     * @param Collection<string, Builder> $queries keyed by post type
     * @return void
     */
    protected function setResult(int $fid, Collection $queries): void
    {
        /**
         * @param Builder $qb
         * @return Builder
         */
        $addOrderByForBuilder = fn (Builder $qb) =>
            $qb->addSelect($this->orderByField)->orderBy($this->orderByField, $this->orderByDirection);
        /** @var array{callback: \Closure(PostModel): mixed, descending: bool}|null $resultSortByParams */
        $resultSortByParams = [
            'callback' => fn (PostModel $i) => $i->getAttribute($this->orderByField),
            'descending' => $this->orderByDirection === 'DESC'
        ];

        /** @var Collection<string, CursorPaginator> $paginators keyed by post type */
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
            'hasMorePages' => self::unionPageStats($paginators, 'hasMorePages',
                static fn (Collection $v) => $v->filter()->count() !== 0) // Collection->filter() will remove false values
        ];
    }

    /**
     * Union builders pagination $unionMethodName data by $unionStatement
     *
     * @param Collection<CursorPaginator> $paginators
     * @param string $unionMethodName
     * @param callable $unionCallback (Collection)
     * @return mixed returned by $unionCallback()
     */
    private static function unionPageStats(Collection $paginators, string $unionMethodName, callable $unionCallback): mixed
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
        /**
         * @param string $postIDName
         * @return array{0: array<int>, 1: Collection<PostModel>}
         */
        $getPostsAndIDTuple = static function (string $postIDName) use ($result): array {
            $postTypePluralName = Helper::POST_ID_TO_TYPE_PLURAL[$postIDName];
            return \array_key_exists($postTypePluralName, $result)
                ? [$result[$postTypePluralName], $result[$postTypePluralName]->pluck($postIDName)->toArray()]
                : [collect(), []];
        };
        /** @var array<int> $tids */
        /** @var array<int> $pids */
        /** @var array<int> $spids */
        /** @var Collection<ThreadModel> $threads */
        /** @var Collection<ReplyModel> $replies */
        /** @var Collection<SubReplyModel> $subReplies */
        [[, $tids], [$replies, $pids], [$subReplies, $spids]] =
            array_map(static fn (string $postIDName) => $getPostsAndIDTuple($postIDName), Helper::POST_ID);

        /** @var int $fid */
        $fid = $result['fid'];
        $postModels = PostModelFactory::getPostModelsByFid($fid);

        /** @var Collection<int> $parentThreadsID parent tid of all replies and their sub replies */
        $parentThreadsID = $replies->pluck('tid')->concat($subReplies->pluck('tid'))->unique();
        $threads = $postModels['thread']
            ->tid($parentThreadsID->concat($tids)) // from the original $this->queryResult, see PostModel::scopeSelectCurrentAndParentPostID()
            ->hidePrivateFields()->get()
            ->map(static fn(ThreadModel $t) => // mark threads that in the original $this->queryResult
                $t->setAttribute('isQueryMatch', \in_array($t->tid, $tids, true)));

        /** @var Collection<int> $parentRepliesID parent pid of all sub replies */
        $parentRepliesID = $subReplies->pluck('pid')->unique();
        $replies = $postModels['reply']
            ->pid($parentRepliesID->concat($pids)) // from the original $this->queryResult, see PostModel::scopeSelectCurrentAndParentPostID()
            ->hidePrivateFields()->get()
            ->map(static fn(ReplyModel $r) => // mark replies that in the original $this->queryResult
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
        $parseProtoBufContent = static function (?string $content): ?string {
            if ($content === null) {
                return null;
            }
            $proto = new PostContentWrapper();
            $proto->mergeFromString($content);
            return str_replace("\n", '', trim(view('renderPostContent', ['content' => $proto->getValue()])->render()));
        };
        $parseContentModel = static fn (Model $i) => $parseProtoBufContent($i->content);
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
                ->pid($replies->pluck('pid'))->get()->keyBy('pid')->map($parseContentModel);
            $replies->transform($appendParsedContent($replyContents, 'pid'));
        }
        if ($subReplies->isNotEmpty()) {
            /** @var Collection<?string> $subReplyContents */
            $subReplyContents = PostModelFactory::newSubReplyContent($fid)
                ->spid($subReplies->pluck('spid'))->get()->keyBy('spid')->map($parseContentModel);
            $subReplies->transform($appendParsedContent($subReplyContents, 'spid'));
        }
    }

    public static function nestPostsWithParent(Collection $threads, Collection $replies, Collection $subReplies, ...$_): array
    {
        // the useless spread parameter $_ will compatible with the array shape which returned by $this->fillWithParentPost()
        return $threads->map(fn (ThreadModel $thread) => [
            ...$thread->toArray(),
            'replies' => $replies->where('tid', $thread->tid)->values() // remove numeric indexed keys
                ->map(fn (ReplyModel $reply) => [
                    ...$reply->toArray(),
                    'subReplies' => $subReplies->where('pid', $reply->pid)->values()
                ])
        ])->toArray();
    }
}
