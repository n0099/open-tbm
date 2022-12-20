<?php

namespace App\Http\PostsQuery;

use App\Helper;
use App\Tieba\Eloquent\PostModel;
use App\Tieba\Eloquent\PostModelFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use JetBrains\PhpStorm\ArrayShape;
use TbClient\Wrapper\PostContentWrapper;

trait BaseQuery
{
    #[ArrayShape([
        'fid' => 'int',
        'threads' => '?array<array>',
        'replies' => '?array<array>',
        'subReplies' => '?array<array>',
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

    protected function setResult(int $fid, Collection $paginators, Collection $results): void
    {
        Helper::abortAPIIf(40401, $results->every(static fn (Collection $i) => $i->isEmpty()));
        $this->queryResult = ['fid' => $fid, ...$results->map(static fn (Collection $i) => $i->toArray())->toArray()];
        $this->queryResultPages = [
            'firstItem' => self::unionPageStats($paginators, 'firstItem', static fn (array $v) => min($v)),
            'itemCount' => self::unionPageStats($paginators, 'count', static fn (array $v) => array_sum($v)),
            'currentPage' => self::unionPageStats($paginators, 'currentPage', static fn (array $v) => min($v))
        ];
    }

    /**
     * Union builders pagination $unionMethodName data by $unionStatement
     *
     * @param Collection<Paginator> $paginators
     * @param string $unionMethodName
     * @param callable $unionCallback
     * @return mixed returned by $unionCallback()
     */
    private static function unionPageStats(Collection $paginators, string $unionMethodName, callable $unionCallback): mixed
    {
        // Collection::filter() will remove falsy values
        $unionValues = $paginators->map(static fn ($p) => $p->$unionMethodName())->filter()->toArray();
        return $unionCallback($unionValues === [] ? [0] : $unionValues); // prevent empty array
    }

    public function fillWithParentPost(): array
    {
        $result = $this->queryResult;
        $tryPluckPostsID = function (string $postIDName) use ($result): array {
            $postTypePluralName = Helper::POST_ID_TO_TYPE_PLURAL[$postIDName];
            return \array_key_exists($postTypePluralName, $result) ? array_column($result[$postTypePluralName], $postIDName) : [];
        };
        /** @var array<int> $tids */
        /** @var array<int> $pids */
        /** @var array<int> $spids */
        [$tids, $pids, $spids] = array_map(fn (string $postIDName) => $tryPluckPostsID($postIDName), Helper::POSTS_ID);

        /** @var int $fid */
        $fid = $result['fid'];
        $postModels = PostModelFactory::getPostModelsByFid($fid);
        $shouldQueryDetailedPosts = $this instanceof IndexQuery;
        /**
         * @param array<int> $postIDs
         * @param string $postType
         * @return Collection<PostModel>
         */
        $tryQueryDetailedPosts = static function (array $postIDs, string $postType) use ($result, $postModels, $shouldQueryDetailedPosts) {
            if ($postIDs === []) {
                return collect();
            }
            return collect($shouldQueryDetailedPosts
                ? $postModels[$postType]->where(Helper::POST_TYPE_TO_ID[$postType], $postIDs)
                    ->hidePrivateFields()->get()
                : $result[Helper::POST_TYPE_TO_PLURAL[$postType]]);
        };
        /** @var Collection<PostModel> $threads */
        /** @var Collection<PostModel> $replies */
        /** @var Collection<PostModel> $subReplies */
        [$threads, $replies, $subReplies] = array_map(
            fn (array $ids, string $type) => $tryQueryDetailedPosts($ids, $type),
            [$tids, $pids, $spids],
            Helper::POST_TYPES
        );

        /**
         * @param Collection<int> $parentIDs
         * @param Collection<int> $subIDs
         * @return bool
         */
        $isSubPostIDMissFormParent = static fn (Collection $parentIDs, Collection $subIDs) =>
            $subIDs->contains(static fn (int $subID) => !$parentIDs->contains($subID));

        /** @var Collection<int> $tidsInRepliesAndSubReplies */
        $tidsInRepliesAndSubReplies = $replies->pluck('tid')
            ->concat($subReplies->pluck('tid'))->unique()->sort()->values();
        // $tids must be first argument to ensure the existence of diffed $tidsInRepliesAndSubReplies
        if ($isSubPostIDMissFormParent(collect($tids), $tidsInRepliesAndSubReplies)) {
            // fetch complete threads info which appeared in replies and sub replies info but missing in $tids
            $threads = collect($postModels['thread']
                ->tid($tidsInRepliesAndSubReplies->concat($tids)->toArray())
                ->hidePrivateFields()->get());
        }

        /** @var Collection<int> $pidsInSubReplies */
        $pidsInSubReplies = $subReplies->pluck('pid')->unique()->sort()->values();
        // $pids must be first argument to ensure the diffed $pidsInSubReplies existing
        if ($isSubPostIDMissFormParent(collect($pids), $pidsInSubReplies)) {
            // fetch complete replies info, which appeared in threads and sub replies info but missing in $pids
            $replies = collect($postModels['reply']
                ->pid($pidsInSubReplies->concat($pids)->toArray())
                ->hidePrivateFields()->get());
        }

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
            $replyContents = PostModelFactory::newReplyContent($fid)->pid($replies->pluck('pid'))->get()->keyBy('pid')->map($parseContentModel);
            $replies->transform($appendParsedContent($replyContents, 'pid'));
        }
        if ($subReplies->isNotEmpty()) {
            /** @var Collection<?string> $subReplyContents */
            $subReplyContents = PostModelFactory::newSubReplyContent($fid)->spid($subReplies->pluck('spid'))->get()->keyBy('spid')->map($parseContentModel);
            $subReplies->transform($appendParsedContent($subReplyContents, 'spid'));
        }

        return [
            'fid' => $fid,
            ...array_combine(Helper::POST_TYPES_PLURAL, [$threads->toArray(), $replies->toArray(), $subReplies->toArray()])
        ];
    }

    public static function nestPostsWithParent(array $threads, array $replies, array $subReplies, int $fid): array
    {
        // the useless parameter $fid will compatible with array shape of field $this->queryResult when passing it as spread arguments
        $threads = Helper::keyBy($threads, 'tid');
        $replies = Helper::keyBy($replies, 'pid');
        $subReplies = Helper::keyBy($subReplies, 'spid');
        $nestedPosts = [];

        foreach ($threads as $tid => $thread) {
            // can't invoke values() here to prevent losing key with posts ID
            $threadReplies = collect($replies)->where('tid', $tid)->toArray();
            foreach ($threadReplies as $pid => $reply) {
                // values() and array_values() remove keys to simplify json data
                $threadReplies[$pid]['subReplies'] = collect($subReplies)->where('pid', $pid)->values()->toArray();
            }
            $nestedPosts[$tid] = [...$thread, 'replies' => array_values($threadReplies)];
        }

        return array_values($nestedPosts);
    }
}
