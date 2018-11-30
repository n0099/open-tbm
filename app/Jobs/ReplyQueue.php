<?php

namespace App\Jobs;

use App\Eloquent\CrawlingPostModel;
use DemeterChain\C;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Tieba\Crawler;
use App\Tieba\Eloquent;

class ReplyQueue extends CrawlerQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $forumId;

    private $threadId;

    private $queuePushTime;

    public function __construct(int $fid, int $tid)
    {
        Log::info('reply queue constructed with' . "{$tid} in forum {$fid}");

        $this->forumId = $fid;
        $this->threadId = $tid;
        $this->queuePushTime = microtime(true);
    }

    public function handle()
    {
        $queueStartTime = microtime(true);
        Log::info('reply queue start after waiting for ' . ($queueStartTime - $this->queuePushTime));

        $repliesCrawler = (new Crawler\ReplyCrawler($this->forumId, $this->threadId))->doCrawl();
        $newRepliesInfo = $repliesCrawler->getRepliesInfo();
        $oldRepliesInfo = self::convertIDListKey(Eloquent\ModelFactory::newReply($this->forumId)
            ->select('pid', 'subReplyNum')->whereIn('pid', array_keys($newRepliesInfo))->get()->toArray(), 'pid');
        $repliesCrawler->saveLists();
        echo 'reply:' . memory_get_usage() . PHP_EOL;

        \DB::transaction(function () use ($newRepliesInfo, $oldRepliesInfo) {
            $latestCrawlingReplies = CrawlingPostModel::select('id', 'pid', 'startTime')
                ->whereIn('pid', array_keys($newRepliesInfo))->lockForUpdate()->get();
            $latestCrawlingRepliesID = array_column($latestCrawlingReplies->toArray(), 'pid');
            foreach ($newRepliesInfo as $pid => $newReply) {
                if (array_search($pid, $latestCrawlingRepliesID) === true) {
                    if ($latestCrawlingReplies->startTime < new Carbon($this->queueDeleteAfter)) {
                        $latestCrawlingReplies->delete();
                    } else {
                        continue;
                    }
                }
                if ((! isset($oldRepliesInfo[$pid]))
                    || ($newReply['subReplyNum'] != $oldRepliesInfo[$pid]['subReplyNum'])) {
                    CrawlingPostModel::insert([
                        'fid' => $this->forumId,
                        'tid' => $this->threadId,
                        'pid' => $pid,
                        'startTime' => microtime(true)
                    ]); // report crawling sub replies
                    //(new Crawler\SubReplyCrawler($this->forumId, $this->threadId, $pid))->doCrawl()->saveLists();
                    SubReplyQueue::dispatch($this->forumId, $this->threadId, $pid);
                }
            }
        });

        $queueFinishTime = microtime(true);
        // report finished reply crawl
        $currentCrawlingReply = CrawlingPostModel::select('id', 'startTime')->where(['tid' => $this->threadId, 'pid' => 0])->first();
        $currentCrawlingReply->fill(['duration' => $queueFinishTime - $currentCrawlingReply->startTime])->save();
        $currentCrawlingReply->delete();
        Log::info('reply queue handled after ' . ($queueFinishTime - $queueStartTime));
    }
}
