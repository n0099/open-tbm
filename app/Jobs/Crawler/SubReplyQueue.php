<?php

namespace App\Jobs\Crawler;

use App\Eloquent\CrawlingPostModel;
use App\Tieba\Crawler;
use App\TimingHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubReplyQueue extends CrawlerQueue implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected int $fid;

    protected int $tid;

    protected int $pid;

    protected int $startPage = 1; // hardcoded crawl start page

    public function __construct(int $fid, int $tid, int $pid)
    {
        \Log::channel('crawler-info')->info("Sub reply queue dispatched, fid:{$fid}, tid:{$tid}, pid:{$pid}");

        $this->fid = $fid;
        $this->tid = $tid;
        $this->pid = $pid;
    }

    public function handle()
    {
        $queueTiming = new TimingHelper();
        \DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'); // change present crawler queue session's transaction isolation level to reduce deadlock

        $firstPageCrawler = (new Crawler\SubReplyCrawler($this->fid, $this->tid, $this->pid, $this->startPage))->doCrawl()->savePostsInfo();
        $lastPageCrawler = null;
        // crawl last page sub reply if there's more than one page
        $subRepliesListLastPage = $firstPageCrawler->getPages()['total_page'] ?? 0;
        if ($subRepliesListLastPage > $this->startPage) { // don't have to crawl every sub reply pages, only first and last one
            $lastPageCrawler = (new Crawler\SubReplyCrawler($this->fid, $this->tid, $this->pid, $subRepliesListLastPage))->doCrawl()->savePostsInfo();
        }

        $queueTiming->stop();
        \DB::transaction(function () use ($firstPageCrawler, $lastPageCrawler, $queueTiming) {
            $crawlerProfiles = [];
            if ($lastPageCrawler != null) {
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
                    'tid' => $this->tid,
                    'pid' => $this->pid
                ])
                ->lockForUpdate()->first();
            if ($currentCrawlingSubReply != null) { // might already marked as finished by other concurrency queues
                $currentCrawlingSubReply->fill([
                    'queueTiming' => $queueTiming->getTiming()
                ] + $crawlerProfiles)->save();
                $currentCrawlingSubReply->delete(); // release current crawl queue lock
            }
        });

        \Log::channel('crawler-info')->info('Sub reply queue completed after ' . ($queueTiming->getTiming()));
    }
}
