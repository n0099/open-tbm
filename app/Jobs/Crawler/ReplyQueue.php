<?php

namespace App\Jobs\Crawler;

use App\Eloquent\CrawlingPostModel;
use App\Helper;
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

    protected $queueStartTime;

    protected $forumID;

    protected $threadID;

    protected $startPage;

    public function __construct(int $fid, int $tid, int $startPage)
    {
        Log::info("Reply crawler queue dispatched with {$tid} in forum {$fid}, starts from page {$startPage}");

        $this->forumID = $fid;
        $this->threadID = $tid;
        $this->startPage = $startPage;
    }

    public function handle()
    {
        $this->queueStartTime = microtime(true);
        \DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'); // change present crawler queue session's transaction isolation level to reduce deadlock

        $repliesCrawler = (new Crawler\ReplyCrawler($this->forumID, $this->threadID, $this->startPage))->doCrawl();

        $newRepliesInfo = $repliesCrawler->getRepliesInfo();
        $oldRepliesInfo = Helper::convertIDListKey(
            PostModelFactory::newReply($this->forumID)
                ->select('pid', 'subReplyNum')
                ->whereIn('pid', array_keys($newRepliesInfo))
                ->get()->toArray(),
            'pid'
        );
        ksort($oldRepliesInfo);
        $repliesCrawler->saveLists();

        \DB::transaction(function () use ($newRepliesInfo, $oldRepliesInfo) {
            $parallelCrawlingSubReplies = CrawlingPostModel
                ::select('id', 'pid', 'startTime')
                ->where('type', 'subReply')
                ->whereIn('pid', array_keys($newRepliesInfo))
                ->lockForUpdate()->get();
            foreach ($newRepliesInfo as $pid => $newReply) {
                foreach ($parallelCrawlingSubReplies as $parallelCrawlingSubReply) {
                    if ($parallelCrawlingSubReply->pid == $pid) {
                        if ($parallelCrawlingSubReply->startTime < new Carbon($this->queueDeleteAfter)) {
                            $parallelCrawlingSubReply->delete(); // release latest parallel sub reply crawler lock then dispatch new crawler when it's has started before $queueDeleteAfter ago
                        } else {
                            continue 2; // cancel pending reply's sub reply crawl because it's already crawling by other queue
                        }
                    }
                }
                if (! isset($oldRepliesInfo[$pid]) // do we have to crawl new sub replies under reply
                    || ($newReply['subReplyNum'] != $oldRepliesInfo[$pid]['subReplyNum'])) {
                    $firstSubReplyCrawlPage = 1;
                    CrawlingPostModel::insert([
                        'type' => 'subReply',
                        'fid' => $this->forumID,
                        'tid' => $this->threadID,
                        'pid' => $pid,
                        'startPage' => $firstSubReplyCrawlPage,
                        'startTime' => microtime(true)
                    ]); // lock for current reply's sub reply crawler
                    SubReplyQueue::dispatch($this->forumID, $this->threadID, $pid, $firstSubReplyCrawlPage)->onQueue('crawler');
                }
            }
        });

        $queueFinishTime = microtime(true);
        \DB::transaction(function () use ($queueFinishTime, $repliesCrawler) {
            // report previous thread crawl finished
            $currentCrawlingReply = CrawlingPostModel::select('id', 'startTime')->where([
                'type' => 'reply', // not including sub reply crawler queue
                'fid' => $this->forumID,
                'tid' => $this->threadID,
                'startPage' => $this->startPage
            ])->first();
            if ($currentCrawlingReply != null) { // might already marked as finished by other concurrency queues
                $currentCrawlingReply->fill([
                    'duration' => $queueFinishTime - $this->queueStartTime
                ] + $repliesCrawler->getTimes())->save();
                $currentCrawlingReply->delete();
            }

            // dispatch new self crawler which starts from current crawler's end page
            if ($repliesCrawler->endPage < $repliesCrawler->getPages()['total_page']) {
                $newCrawlerStartPage = $repliesCrawler->endPage + 1;
                $parallelCrawlingReply = CrawlingPostModel
                    ::select('id', 'tid', 'startTime', 'startPage')
                    ->where([
                        'type' => 'reply',
                        'fid' => $this->forumID,
                        'tid' => $this->threadID,
                    ])
                    ->lockForUpdate()->first();
                if ($parallelCrawlingReply != null) { // is latest parallel reply crawler existed and started before $queueDeleteAfter ago
                    if ($parallelCrawlingReply->startTime < new Carbon($this->queueDeleteAfter)
                        || $parallelCrawlingReply->startPage < $repliesCrawler->startPage) {
                        $parallelCrawlingReply->delete();
                    } else {
                        return; // cancel pending next page range reply crawl because it's already crawling by other queue
                    }
                }
                CrawlingPostModel::insert([
                    'type' => 'reply',
                    'fid' => $this->forumID,
                    'tid' => $this->threadID,
                    'startPage' => $newCrawlerStartPage,
                    'startTime' => microtime(true)
                ]); // lock for next page range reply crawler
                ReplyQueue::dispatch($this->forumID, $this->threadID, $newCrawlerStartPage)->onQueue('crawler');
            }
        });
        Log::info('Reply crawler queue completed after ' . ($queueFinishTime - $this->queueStartTime));
    }
}
