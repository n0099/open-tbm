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

    protected $forumID;

    private $threadID;

    private $replyID;

    private $queuePushTime;

    public function __construct(int $fid, int $tid, int $pid)
    {
        Log::info("sub reply queue constructed with {$tid} in forum {$fid}");

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

        $queueFinishTime = microtime(true);
        \DB::transaction(function () use ($queueFinishTime) {
            // report previous reply crawl finished
            $perviousCrawlingReply = CrawlingPostModel::select('id', 'startTime')->where(['tid' => $this->threadID, 'pid' => $this->replyID])->first();
            if ($perviousCrawlingReply != null) { // might already marked as finished by other concurrency queues
                $perviousCrawlingReply->fill(['duration' => $queueFinishTime - $perviousCrawlingReply->startTime])->save();
                $perviousCrawlingReply->delete();
            }
        });
        Log::info('sub reply queue handled after ' . ($queueFinishTime - $queueStartTime));
    }
}
