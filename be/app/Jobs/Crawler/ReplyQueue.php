<?php

namespace App\Jobs\Crawler;

use App\Eloquent\CrawlingPostModel;
use App\Helper;
use App\Tieba\Crawler;
use App\Tieba\Eloquent\PostModelFactory;
use App\Timer;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReplyQueue extends CrawlerQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        protected int $fid,
        protected int $tid,
        protected int $startPage
    ) {
        \Log::channel('crawler-info')->info("Reply crawler queue dispatched, fid:{$fid}, tid:{$tid}, startPage:{$startPage}");
    }

    public function handle(): void
    {
        $queueTimer = new Timer();
        \DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'); // change present crawler queue session's transaction isolation level to reduce deadlock

        $repliesCrawler = (new Crawler\ReplyCrawler($this->fid, $this->tid, $this->startPage))->doCrawl();
        $newRepliesInfo = $repliesCrawler->getUpdatedPostsInfo();
        $oldRepliesInfo = Helper::setKeyWithItemsValue(
            PostModelFactory::newReply($this->fid)
                ->select('pid', 'subReplyNum')
                ->whereIn('pid', array_keys($newRepliesInfo))
                ->get()->toArray(),
            'pid'
        );
        ksort($oldRepliesInfo);
        $repliesCrawler->savePostsInfo();

        \DB::transaction(function () use ($newRepliesInfo, $oldRepliesInfo) {
            $parallelCrawlingSubReplies = CrawlingPostModel
                ::select('id', 'pid', 'startTime')
                ->where('type', 'subReply')
                ->whereIn('pid', array_keys($newRepliesInfo))
                ->lockForUpdate()->get();
            foreach ($newRepliesInfo as $pid => $newReply) {
                // check for other parallelling sub reply crawler lock
                foreach ($parallelCrawlingSubReplies as $parallelCrawlingSubReply) {
                    if ($parallelCrawlingSubReply->pid === $pid) {
                        if ($parallelCrawlingSubReply->startTime < new Carbon($this->queueDeleteAfter)) {
                            $parallelCrawlingSubReply->delete(); // release latest parallel sub reply crawler lock then dispatch new one when it had started before $queueDeleteAfter ago
                        } else {
                            continue 2; // cancel pending reply's sub reply crawl because it's already crawling
                        }
                    }
                }
                if (! isset($oldRepliesInfo[$pid]) // do we have to crawl new sub replies under reply
                    || (int)$newReply['subReplyNum'] !== $oldRepliesInfo[$pid]['subReplyNum']) {
                    CrawlingPostModel::insert([
                        'type' => 'subReply',
                        'fid' => $this->fid,
                        'tid' => $this->tid,
                        'pid' => $pid,
                        'startTime' => microtime(true)
                    ]); // lock for current reply's sub reply crawler
                    SubReplyQueue::dispatch($this->fid, $this->tid, $pid)->onQueue('crawler');
                }
            }
        });

        $queueTimer->stop();
        \DB::transaction(function () use ($repliesCrawler, $queueTimer) {
            // report current crawl queue finished
            $currentCrawlingReply = CrawlingPostModel
                ::select('id', 'startTime')
                ->where([
                    'type' => 'reply', // not including current reply's sub reply crawler
                    'fid' => $this->fid,
                    'tid' => $this->tid
                ])
                ->lockForUpdate()->first();
            if ($currentCrawlingReply !== null) { // might already marked as finished by other concurrency queues
                $currentCrawlingReply->fill(array_merge($repliesCrawler->getProfiles(), [
                    'queueTiming' => $queueTimer->getTime()
                ]))->save();
                $currentCrawlingReply->delete(); // release current crawl queue lock
            }

            // dispatch next page range crawler if there's un-crawled pages
            if ($repliesCrawler->endPage < ($repliesCrawler->getPages()['total_page'] ?? 0)) {
                $newCrawlerStartPage = $repliesCrawler->endPage + 1;
                CrawlingPostModel::insert([
                    'type' => 'reply',
                    'fid' => $this->fid,
                    'tid' => $this->tid,
                    'startPage' => $newCrawlerStartPage,
                    'startTime' => microtime(true)
                ]); // lock for next page range reply crawler
                ReplyQueue::dispatch($this->fid, $this->tid, $newCrawlerStartPage)->onQueue('crawler');
            }
        });

        \Log::channel('crawler-info')->info('Reply crawler queue completed after ' . ($queueTimer->getTime()));
    }
}
