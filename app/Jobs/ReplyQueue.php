<?php

namespace App\Jobs;

use App\Eloquent\CrawlingPostModel;
use App\Tieba\Crawler;
use App\Tieba\Eloquent\PostModelFactory;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReplyQueue extends CrawlerQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $forumID;

    private $threadID;

    private $queuePushTime;

    public function __construct(int $fid, int $tid)
    {
        Log::info("Reply crawler queue constructed with {$tid} in forum {$fid}");

        $this->forumID = $fid;
        $this->threadID = $tid;
        $this->queuePushTime = microtime(true);
    }

    public function handle()
    {
        $queueStartTime = microtime(true);
        Log::info('Reply crawler queue start after waiting for ' . ($queueStartTime - $this->queuePushTime));
        \DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'); // change present crawler queue session's transaction isolation level to reduce deadlock

        $repliesCrawler = (new Crawler\ReplyCrawler($this->forumID, $this->threadID))->doCrawl();
        $newRepliesInfo = $repliesCrawler->getRepliesInfo();
        $oldRepliesInfo = static::convertIDListKey(
            PostModelFactory::newReply($this->forumID)
                ->select('pid', 'subReplyNum')
                ->whereIn('pid', array_keys($newRepliesInfo))->get()->toArray(),
            'pid'
        );
        $repliesCrawler->saveLists();

        \DB::transaction(function () use ($newRepliesInfo, $oldRepliesInfo) {
            $previousCrawlingSubReplies = CrawlingPostModel::select('id', 'pid', 'startTime')
                ->type(['subReply'])->whereIn('pid', array_keys($newRepliesInfo))->lockForUpdate()->get();
            foreach ($newRepliesInfo as $pid => $newReply) {
                foreach ($previousCrawlingSubReplies as $previousCrawlingSubReply) {
                    if ($previousCrawlingSubReply->pid == $pid // is latest sub reply crawler existed and started before $queueDeleteAfter ago
                        || $previousCrawlingSubReply->startTime < new Carbon($this->queueDeleteAfter)) {
                        $previousCrawlingSubReply->delete();
                    } else {
                        continue 2; // skip current reply's sub reply crawl
                    }
                }
                if ((! isset($oldRepliesInfo[$pid])) // do we have to crawl new sub replies under reply
                    || ($newReply['subReplyNum'] != $oldRepliesInfo[$pid]['subReplyNum'])) {
                    CrawlingPostModel::insert([
                        'type' => 'subReply',
                        'fid' => $this->forumID,
                        'tid' => $this->threadID,
                        'pid' => $pid,
                        'startTime' => microtime(true)
                    ]); // report crawling sub replies
                    SubReplyQueue::dispatch($this->forumID, $this->threadID, $pid)->onQueue('crawler');
                }
            }
        });

        $queueFinishTime = microtime(true);
        \DB::transaction(function () use ($queueFinishTime) {
            // report previous thread crawl finished
            $currentCrawlingReply = CrawlingPostModel::select('id', 'startTime')->where([
                'type' => 'reply', // not including sub reply crawler queue
                'tid' => $this->threadID,
                'pid' => 0
            ])->first();
            if ($currentCrawlingReply != null) { // might already marked as finished by other concurrency queues
                $currentCrawlingReply->fill(['duration' => $queueFinishTime - $currentCrawlingReply->startTime])->save();
                $currentCrawlingReply->delete();
            }
        });
        Log::info('Reply crawler queue completed after ' . ($queueFinishTime - $queueStartTime));
    }
}
