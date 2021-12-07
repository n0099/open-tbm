<?php

namespace App\Jobs\Crawler;

use App\Eloquent\CrawlingPostModel;
use App\Tieba\Crawler;
use App\Timer;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubReplyQueue extends CrawlerQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected int $startPage = 1; // hardcoded crawl start page

    public function __construct(
        protected int $fid,
        protected int $tid,
        protected int $pid
    ) {
        \Log::channel('crawler-info')->info("Sub reply queue dispatched, fid:{$fid}, tid:{$tid}, pid:{$pid}");
    }

    public function handle(): void
    {
        $queueTimer = new Timer();
        \DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'); // change present crawler queue session's transaction isolation level to reduce deadlock

        $crawlerProfiles = (new Crawler\SubReplyCrawler($this->fid, $this->tid, $this->pid, $this->startPage, PHP_INT_MAX))
            ->doCrawl()->savePostsInfo()->getProfiles();

        $queueTimer->stop();
        \DB::transaction(function () use ($crawlerProfiles, $queueTimer) {
            // report previous reply crawl finished
            /** @var Model|null $currentCrawlingSubReply */
            $currentCrawlingSubReply = CrawlingPostModel
                ::select('id', 'startTime')
                ->where([
                    'type' => 'subReply',
                    'tid' => $this->tid,
                    'pid' => $this->pid
                ])
                ->lockForUpdate()->first();
            if ($currentCrawlingSubReply !== null) { // might already mark as finished by other concurrency queues
                $currentCrawlingSubReply->fill(array_merge($crawlerProfiles, [
                    'queueTiming' => $queueTimer->getTime()
                ]))->save();
                $currentCrawlingSubReply->delete(); // release current crawl queue lock
            }
        });

        \Log::channel('crawler-info')->info('Sub reply queue completed after ' . ($queueTimer->getTime()));
    }
}
