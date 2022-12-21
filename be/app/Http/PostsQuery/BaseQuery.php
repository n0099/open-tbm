<?php

namespace App\Http\PostsQuery;

use App\Helper;
use App\Tieba\Eloquent\PostModel;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\Eloquent\ReplyModel;
use App\Tieba\Eloquent\SubReplyModel;
use App\Tieba\Eloquent\ThreadModel;
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

    abstract public function query(QueryParams $params): self;

    public function __construct(protected int $perPageItems)
    {
    }

    public function getResultPages(): array
    {
        return $this->queryResultPages;
    }

    protected function setResult(int $fid, Collection $paginators, Collection $resultKeyByPostTypePluralName): void
    {
        Helper::abortAPIIf(40401, $resultKeyByPostTypePluralName->every(static fn (Collection $i) => $i->isEmpty()));
        $this->queryResult = ['fid' => $fid, ...$resultKeyByPostTypePluralName];
        $this->queryResultPages = [
            'hasMorePages' => self::unionPageStats($paginators, 'hasMorePages',
                static fn (Collection $v) => $v->filter()->count() !== 0), // Collection->filter() will remove false values
            'queryMatchCount' => self::unionPageStats($paginators, 'count', static fn (Collection $v) => $v->sum())
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
            'parentThreadCount' => $parentThreadsID->count(),
            'parentReplyCount' => $parentRepliesID->count(),
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
