<?php

namespace App\Http\Controllers;

use App\Eloquent\IndexModel;
use App\Tieba\Eloquent\PostModelFactory;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;

class SearchController extends Controller
{
    protected static function convertIDListKey(array $list, string $keyName): array
    {
        // same with \App\Jobs\CrawlerQueue::convertIDListKey()
        $newList = [];

        foreach ($list as $item) {
            $newList[$item[$keyName]] = $item;
        }
        ksort($newList);

        return $newList;
    }

    private function getNestedThreadsInfoByIDs(int $fid, array $tids = [], array $pids = [], array $spids = []): array
    {
        $postsModel = [
            'thread' => PostModelFactory::newThread($fid),
            'reply' => PostModelFactory::newReply($fid),
            'subReply' => PostModelFactory::newSubReply($fid)
        ];
        $threadsInfo = empty($tids) ? collect() : $postsModel['thread']->tid($tids)->get();
        $repliesInfo = empty($pids) ? collect() : $postsModel['reply']->pid($pids)->get();
        $subRepliesInfo = empty($spids) ? collect() : $postsModel['subReply']->spid($spids)->get();
        $repliesInfo->each(function ($reply) {
            debug($reply->toPost());
        });

        if ($threadsInfo->isEmpty()) {
            if ($repliesInfo->isNotEmpty()) {
                $threadsInfo = $postsModel['thread']->tid($repliesInfo->pluck('tid')->toArray())->get();
            } elseif ($subRepliesInfo->isNotEmpty()) {
                $threadsInfo = $postsModel['thread']->tid($subRepliesInfo->pluck('tid')->toArray())->get();
            } else {
                throw new \InvalidArgumentException('Posts IDs is empty');
            }
        }
        if ($repliesInfo->isEmpty()) {
            $repliesInfo = $postsModel['reply']->pid($subRepliesInfo->pluck('pid')->toArray())->get();
        }
        if ($subRepliesInfo->isEmpty()) {
            $subRepliesInfo = $postsModel['subReply']->spid($repliesInfo->pluck('spid')->toArray())->get();
        }

        return self::convertNestedPostsInfo($threadsInfo->toArray(), $repliesInfo->toArray(), $subRepliesInfo->toArray());
    }

    private static function convertNestedPostsInfo(array $threadsInfo = [], array $repliesInfo = [], array $subRepliesInfo = []): array
    {
        $threadsInfo = self::convertIDListKey($threadsInfo, 'tid');
        $repliesInfo = self::convertIDListKey($repliesInfo, 'pid');
        $subRepliesInfo = self::convertIDListKey($subRepliesInfo, 'spid');

        $nestedPostsInfo = [];
        foreach ($threadsInfo as $tid => $thread) {
            $threadReplies = collect($repliesInfo)->where('tid', $tid)->toArray();
            foreach ($threadReplies as $pid => $reply) {
                // values() and array_values remove keys to simplify json data
                $threadReplies[$pid]['subReplies'] = collect($subRepliesInfo)->where('pid', $pid)->values()->toArray();
            }
            $nestedPostsInfo[$tid] = $thread + ['replies' => array_values($threadReplies)];
        }

        return array_values($nestedPostsInfo);
    }

    private function index(int $tid = null, int $pid = null, int $spid = null)
    {
        //$json = IndexModel::where('tid', $tid)->paginate(20)->toJson();
        $indexesModel = IndexModel::where(array_filter(['tid' => $tid, 'pid' => $pid, 'spid' => $spid]))
            ->limit(100)->get(); // array_filter will remove null value elements
        start_measure(1);

        $tids = $indexesModel->where('type', 'thread')->pluck('tid')->toArray();
        $pids = $indexesModel->where('type', 'reply')->pluck('pid')->toArray();
        $spids = $indexesModel->where('type', 'subReply')->pluck('spid')->toArray();
        $nestedPostsInfo = self::getNestedThreadsInfoByIDs(228500, $tids, $pids, $spids);
        /*foreach ($postsInfo['replies'] as $pid => $reply) {
            $nestedPostsInfo[$reply['tid']]['replies'][$pid]['subReplies']
                = collect($postsInfo['subReplies'])->where('pid', $pid)->toArray();
        }*/
        /*array_map(function ($item) {
            return array_values($item);
        }, $nestedPostsInfo['threads']);*/
        debug($nestedPostsInfo);
        stop_measure(1);
        return json_encode($nestedPostsInfo);
    }

    public function searchByTid(int $tid, int $pid = null, int $spid = null)
    {
        return $this->index($tid, $pid, $spid);
    }

    public function searchByPid(int $pid, int $spid = null)
    {
        return $this->index(null, $pid, $spid);
    }

    public function searchBySpid(int $spid)
    {
        return $this->index(null, null, $spid);
    }
}
