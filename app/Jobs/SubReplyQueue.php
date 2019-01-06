<?php

namespace App\Jobs;

use App\Eloquent\CrawlingPostModel;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use App\Tieba\Crawler;

class SubReplyQueue extends CrawlerQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $forumID;

    private $threadID;

    private $replyID;

    private $queuePushTime;

    public function __construct(int $fid, int $tid, int $pid)
    {
        Log::info('sub reply queue constructed with' . "{$tid} in forum {$fid}");

        $this->forumID = $fid;
        $this->threadID = $tid;
        $this->replyID = $pid;
        $this->queuePushTime = microtime(true);
    }

    public function handle()
    {
        $queueStartTime = microtime(true);
        Log::info('sub reply queue start after waiting for ' . ($queueStartTime - $this->queuePushTime));

        (new Crawler\SubReplyCrawler($this->forumID, $this->threadID, $this->replyID))->doCrawl()->saveLists();
        echo 'subreply:' . memory_get_usage() . PHP_EOL;

        $queueFinishTime = microtime(true);
        // report finished sub reply crawl
        $currentCrawlingSubReply = CrawlingPostModel::select('id', 'startTime')->where(['tid' => $this->threadID, 'pid' => $this->replyID])->first();
        $currentCrawlingSubReply->fill(['duration' => $queueFinishTime - $currentCrawlingSubReply->startTime])->save();
        $currentCrawlingSubReply->delete();
        Log::info('sub reply queue handled after ' . ($queueFinishTime - $queueStartTime));
    }
}
