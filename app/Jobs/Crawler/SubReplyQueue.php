<?php

namespace App\Jobs\Crawler;

use App\Eloquent\CrawlingPostModel;
use App\Tieba\Crawler;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubReplyQueue extends CrawlerQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $queueStartTime;

    protected $forumID;

    protected $threadID;

    protected $replyID;

    protected $startPage = 1;

    public function __construct(int $fid, int $tid, int $pid)
    {
        \Log::channel('crawler-info')->info("Sub reply queue dispatched with {$tid} in forum {$fid}, starts from page {$this->startPage}");

        $this->forumID = $fid;
        $this->threadID = $tid;
        $this->replyID = $pid;
    }

    public function handle()
    {
        $this->queueStartTime = microtime(true);
        \DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'); // change present crawler queue session's transaction isolation level to reduce deadlock

        $firstPageCrawler = (new Crawler\SubReplyCrawler($this->forumID, $this->threadID, $this->replyID, $this->startPage))->doCrawl()->saveLists();

        $queueFinishTime = microtime(true);
        \DB::transaction(function () use ($queueFinishTime, $firstPageCrawler) {
            // crawl last page sub reply if there's un-crawled pages
            $subRepliesListLastPage = $firstPageCrawler->getPages()['total_page'] ?? 0;  // give up next page range crawl when TiebaException thrown within crawler parser
            if ($subRepliesListLastPage > $this->startPage) { // doesn't have to crawl every sub reply pages, only first and last one
                $lastPageCrawler = (new Crawler\SubReplyCrawler($this->forumID, $this->threadID, $this->replyID, $subRepliesListLastPage))->doCrawl()->saveLists();
            }

            $crawlerProfiles = [];
            if (isset($lastPageCrawler)) {
                $firstPageProfiles = $firstPageCrawler->getProfiles();
                $lastPageProfiles = $lastPageCrawler->getProfiles();
                // sum up first and last page crawler's profiles value
                foreach ($firstPageProfiles as $profileName => $profileValue) {
                    $crawlerProfiles[$profileName] = $profileValue + $lastPageProfiles[$profileName];
                }
            } else {
                $crawlerProfiles = $firstPageCrawler->getProfiles();
            }

            // report previous reply crawl finished
            $currentCrawlingSubReply = CrawlingPostModel
                ::select('id', 'startTime')
                ->where([
                    'type' => 'subReply',
                    'tid' => $this->threadID,
                    'pid' => $this->replyID
                ])
                ->lockForUpdate()->first();
            if ($currentCrawlingSubReply != null) { // might already marked as finished by other concurrency queues
                $currentCrawlingSubReply->fill([
                    'duration' => $queueFinishTime - $this->queueStartTime
                ] + $crawlerProfiles)->save();
                $currentCrawlingSubReply->delete(); // release current crawl queue lock
            }
        });
        \Log::channel('crawler-info')->info('Sub reply queue completed after ' . ($queueFinishTime - $this->queueStartTime));
    }
}
