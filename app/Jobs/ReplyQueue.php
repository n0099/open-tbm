<?php

namespace App\Jobs;

use App\Eloquent\CrawlingPostModel;
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

    protected $forumID;

    private $threadID;

    private $queuePushTime;

    public function __construct(int $fid, int $tid)
    {
        Log::info("reply queue constructed with {$tid} in forum {$fid}");

        $this->forumID = $fid;
        $this->threadID = $tid;
        $this->queuePushTime = microtime(true);
    }

    public function handle()
    {
        $queueStartTime = microtime(true);
        Log::info('reply queue start after waiting for ' . ($queueStartTime - $this->queuePushTime));

        $repliesCrawler = (new Crawler\ReplyCrawler($this->forumID, $this->threadID))->doCrawl();
        $newRepliesInfo = $repliesCrawler->getRepliesInfo();
        $oldRepliesInfo = self::convertIDListKey(
            Eloquent\PostModelFactory::newReply($this->forumID)
                ->select('pid', 'subReplyNum')
                ->whereIn('pid', array_keys($newRepliesInfo))->get()->toArray(),
            'pid'
        );
        $repliesCrawler->saveLists();

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
                        'fid' => $this->forumID,
                        'tid' => $this->threadID,
                        'pid' => $pid,
                        'startTime' => microtime(true)
                    ]); // report crawling sub replies
                    //(new Crawler\SubReplyCrawler($this->forumId, $this->threadId, $pid))->doCrawl()->saveLists();
                    SubReplyQueue::dispatch($this->forumID, $this->threadID, $pid);
                }
            }
        });

        $queueFinishTime = microtime(true);
        \DB::transaction(function () use ($queueFinishTime) {
            // report previous thread crawl finished
            $previousCrawlingThread = CrawlingPostModel::select('id', 'startTime')->where(['tid' => $this->threadID, 'pid' => 0])->first();
            if ($previousCrawlingThread != null) { // might already marked as finished by other concurrency queues
                $previousCrawlingThread->fill(['duration' => $queueFinishTime - $previousCrawlingThread->startTime])->save();
                $previousCrawlingThread->delete();
            }
        });
        Log::info('reply queue handled after ' . ($queueFinishTime - $queueStartTime));
    }
}
