<?php

namespace App\Jobs\Crawler;

use App\Eloquent\CrawlingPostModel;
use App\Tieba\Crawler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SubReplyQueue extends CrawlerQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $queueStartTime;

    protected $forumID;

    protected $threadID;

    protected $replyID;

    protected $startPage;

    public function __construct(int $fid, int $tid, int $pid, int $startPage)
    {
        Log::info("sub reply queue constructed with {$tid} in forum {$fid}, starts from page {$startPage}");

        $this->forumID = $fid;
        $this->threadID = $tid;
        $this->replyID = $pid;
        $this->startPage = $startPage;
    }

    public function handle()
    {
        $this->queueStartTime = microtime(true);
        \DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'); // change present crawler queue session's transaction isolation level to reduce deadlock

        $subRepliesCrawler = (new Crawler\SubReplyCrawler($this->forumID, $this->threadID, $this->replyID, $this->startPage))->doCrawl()->saveLists();

        // dispatch new self crawler which starts from current crawler's end page
        if ($subRepliesCrawler->endPage < $subRepliesCrawler->getPages()['total_page']) {
            $newCrawlerStartPage = $subRepliesCrawler->endPage + 1;
            CrawlingPostModel::insert([
                'type' => 'subReply',
                'fid' => $this->forumID,
                'tid' => $this->threadID,
                'pid' => $this->replyID,
                'startPage' => $newCrawlerStartPage,
                'startTime' => microtime(true)
            ]); // lock for next pages sub reply crawler
            SubReplyQueue::dispatch($this->forumID, $this->threadID, $this->replyID, $newCrawlerStartPage)->onQueue('crawler');
        }

        $queueFinishTime = microtime(true);
        \DB::transaction(function () use ($queueFinishTime, $subRepliesCrawler) {
            // report previous reply crawl finished
            $currentCrawlingSubReply = CrawlingPostModel::select('id', 'startTime')->where([
                'type' => 'subReply',
                'tid' => $this->threadID,
                'pid' => $this->replyID,
                'startPage' => $this->startPage
            ])->first();
            if ($currentCrawlingSubReply != null) { // might already marked as finished by other concurrency queues
                $currentCrawlingSubReply->fill([
                    'duration' => $queueFinishTime - $this->queueStartTime
                ] + $subRepliesCrawler->getTimes())->save();
                $currentCrawlingSubReply->delete();
            }
        });
        Log::info('sub reply queue handled after ' . ($queueFinishTime - $this->queueStartTime));
    }
}
