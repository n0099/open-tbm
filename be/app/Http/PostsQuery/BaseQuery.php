<?php

namespace App\Http\PostsQuery;

use App\Helper;
use App\Tieba\Eloquent\PostModelFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use TbClient\Wrapper\PostContentWrapper;

trait BaseQuery
{
    protected array $queryResult;

    protected array $queryResultPages;

    abstract public function query(QueryParams $params): self;

    public function __construct(protected int $perPageItems)
    {
    }

    public function getResult(): array
    {
        return $this->queryResult;
    }

    public function getResultPages(): array
    {
        return $this->queryResultPages;
    }

    public function fillWithParentPost(): array
    {
        $result = $this->queryResult;
        $fid = $result['fid'];
        $postModels = PostModelFactory::getPostModelsByFid($fid);
        $tids = array_column($result['threads'], 'tid');
        $pids = array_column($result['replies'], 'pid');
        $spids = array_column($result['subReplies'], 'spid');

        $shouldQueryDetailedPosts = $this instanceof IndexQuery;
        $tryQueryDetailedPosts = static function (array $postIDs, string $postType) use ($result, $postModels, $shouldQueryDetailedPosts) {
            if ($postIDs === []) {
                return collect();
            }
            $model = $postModels[$postType];
            return collect($shouldQueryDetailedPosts
                ? $model->{Helper::POSTS_TYPE_ID[$postType]}($postIDs)->hidePrivateFields()->get()->toArray()
                : $result[Helper::POST_TYPES_TO_PLURAL[$postType]]);
        };
        $threads = $tryQueryDetailedPosts($tids, 'thread');
        $replies = $tryQueryDetailedPosts($pids, 'reply');
        $subReplies = $tryQueryDetailedPosts($spids, 'subReply');

        $isSubPostIDMissFormParent = static fn (Collection $parentIDs, Collection $subIDs) =>
            $subIDs->contains(static fn (int $subID) => !$parentIDs->contains($subID));

        $tidsInReplies = $replies->pluck('tid')
            ->concat($subReplies->pluck('tid'))->unique()->sort()->values();
        // $tids must be first argument to ensure the existence of diffed $tidsInReplies
        if ($isSubPostIDMissFormParent(collect($tids), $tidsInReplies)) {
            // fetch complete threads info which appeared in replies and sub replies info but missing in $tids
            $threads = collect($postModels['thread']
                ->tid($tidsInReplies->concat($tids)->toArray())
                ->hidePrivateFields()->get()->toArray());
        }

        $pidsInThreadsAndSubReplies = $subReplies->pluck('pid')
            // append thread's first reply when there's no pid
            // ->concat($pids === [] ? $threads->pluck('firstPid') : [])
            ->unique()->sort()->values();
        // $pids must be first argument to ensure the diffed $pidsInSubReplies existing
        if ($isSubPostIDMissFormParent(collect($pids), $pidsInThreadsAndSubReplies)) {
            // fetch complete replies info, which appeared in threads and sub replies info but missing in $pids
            $replies = collect($postModels['reply']
                ->pid($pidsInThreadsAndSubReplies->concat($pids)->toArray())
                ->hidePrivateFields()->get()->toArray());
        }

        $parseProtoBufContent = static function (string|null $content): string|null {
            if ($content === null) {
                return null;
            }
            $proto = new PostContentWrapper();
            $proto->mergeFromString($content);
            return str_replace("\n", '', trim(view('formatPostJsonContent', ['content' => $proto->getValue()])->render()));
        };
        $parseContentModel = static fn (Model $i) => $parseProtoBufContent($i->content);
        $replyContents = PostModelFactory::newReplyContent($fid)->pid($replies->pluck('pid'))->get()->keyBy('pid')->map($parseContentModel);
        $subReplyContents = PostModelFactory::newSubReplyContent($fid)->spid($subReplies->pluck('spid'))->get()->keyBy('spid')->map($parseContentModel);
        $appendParsedContent = static fn (Collection $contents, string $postIDName) =>
            static function (array $post) use ($contents, $postIDName) {
                $post['content'] = $contents[$post[$postIDName]];
                return $post;
            };
        $replies->transform($appendParsedContent($replyContents, 'pid'));
        $subReplies->transform($appendParsedContent($subReplyContents, 'spid'));

        return [
            'fid' => $fid,
            ...array_combine(Helper::POST_TYPES_PLURAL, [$threads->toArray(), $replies->toArray(), $subReplies->toArray()])
        ];
    }

    public static function nestPostsWithParent(array $threads, array $replies, array $subReplies, int $fid): array
    {
        // adding useless parameter $fid will compatible with array shape of field $this->queryResult when passing it as spread arguments
        $threads = Helper::keyBy($threads, 'tid');
        $replies = Helper::keyBy($replies, 'pid');
        $subReplies = Helper::keyBy($subReplies, 'spid');
        $nestedPosts = [];

        foreach ($threads as $tid => $thread) {
            // can't invoke values() here to prevent losing key with posts id
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
