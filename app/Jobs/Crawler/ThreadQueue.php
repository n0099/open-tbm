<?php

namespace App\Jobs\Crawler;

use App\Eloquent\CrawlingPostModel;
use App\Helper;
use App\Tieba\Crawler\ThreadCrawler;
use App\Tieba\Eloquent\PostModelFactory;
use App\TimingHelper;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ThreadQueue extends CrawlerQueue implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected int $fid;

    protected string $forumName;

    protected int $startPage;

    protected ?int $endPage;

    public function __construct(int $fid, string $forumName, int $startPage = 1, ?int $endPage = null)
    {
        \Log::channel('crawler-info')->info("Thread crawler queue dispatched, fid:{$fid} {$forumName}, startPage:{$startPage}, endPage:{$endPage}");
        $this->fid = $fid;
        $this->forumName = $forumName;
        $this->startPage = $startPage;
        $this->endPage = $endPage;
    }

    public function handle()
    {
        $queueTiming = new TimingHelper();
        \DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'); // change present crawler queue session's transaction isolation level to reduce deadlock

        $cancelCurrentCrawler = false;
        \DB::transaction(function () use ($cancelCurrentCrawler) {
            $crawlingForumInfo = [
                'type' => 'thread', // not including reply and sub reply crawler queue
                'fid' => $this->fid,
                'startPage' => $this->startPage
            ];
            $latestCrawlingForum = CrawlingPostModel
                ::select('id', 'startTime')
                ->where($crawlingForumInfo)
                ->lockForUpdate()->first();
            if ($latestCrawlingForum != null) { // is latest crawler existed and started before $queueDeleteAfter ago
                if ($latestCrawlingForum->startTime < new Carbon($this->queueDeleteAfter)) {
                    $latestCrawlingForum->delete(); // release latest parallel thread crawler lock then dispatch new one when it's has started before $queueDeleteAfter ago
                } else {
                    $cancelCurrentCrawler = true; // cancel pending thread crawl because it's already crawling
                }
            }
            if (! $cancelCurrentCrawler) {
                CrawlingPostModel::insert($crawlingForumInfo + [
                    'startTime' => microtime(true)
                ]); // lock for current pending thread crawler
            }
        });
        if ($cancelCurrentCrawler) {
            return;
        }

        $threadsCrawler = (new ThreadCrawler($this->fid, $this->forumName, $this->startPage, $this->endPage))->doCrawl();
        $newThreadsInfo = $threadsCrawler->getUpdatedPostsInfo();
        $oldThreadsInfo = Helper::setKeyWithItemsValue(
            PostModelFactory::newThread($this->fid)
                ->select('tid', 'latestReplyTime', 'replyNum')
                ->whereIn('tid', array_keys($newThreadsInfo))->get()->toArray(),
            'tid'
        );
        ksort($oldThreadsInfo);
        $threadsCrawler->savePostsInfo();

        \DB::transaction(function () use ($newThreadsInfo, $oldThreadsInfo) {
            $parallelCrawlingReplies = CrawlingPostModel
                ::select('id', 'tid', 'startTime')
                ->whereIn('type', ['reply', 'subReply']) // including sub reply to prevent repeat crawling sub reply's parent reply (optional optimize case)
                ->whereIn('tid', array_keys($newThreadsInfo))
                ->lockForUpdate()->get();
            foreach ($newThreadsInfo as $tid => $newThread) {
                // check for other parallelling reply crawler lock
                foreach ($parallelCrawlingReplies as $parallelCrawlingReply) {
                    if ($parallelCrawlingReply->tid == $tid) {
                        if ($parallelCrawlingReply->startTime < new Carbon($this->queueDeleteAfter)) {
                            $parallelCrawlingReply->delete(); // release latest parallel reply crawler lock then dispatch new crawler when it's has started before $queueDeleteAfter ago
                        } else {
                            continue 2; // cancel pending thread's reply crawl because it's already crawling
                        }
                    }
                }
                if (! isset($oldThreadsInfo[$tid]) // do we have to crawl new replies under thread
                    || strtotime($newThread['latestReplyTime']) != strtotime($oldThreadsInfo[$tid]['latestReplyTime'])
                    || $newThread['replyNum'] != $oldThreadsInfo[$tid]['replyNum']) {
                    $firstReplyCrawlPage = 1;
                    CrawlingPostModel::insert([
                        'type' => 'reply',
                        'fid' => $this->fid,
                        'tid' => $tid,
                        'startPage' => $firstReplyCrawlPage,
                        'startTime' => microtime(true)
                    ]); // lock for current thread's reply crawler
                    ReplyQueue::dispatch($this->fid, $tid, $firstReplyCrawlPage)->onQueue('crawler');
                }
            }
        });

        $queueTiming->stop();
        \DB::transaction(function () use ($threadsCrawler, $queueTiming) {
            // report previous finished forum crawl
            $currentCrawlingForum = CrawlingPostModel
                ::select('id', 'startTime')
                ->where([
                    'type' => 'thread', // not including current thread's reply and sub reply crawler queue
                    'fid' => $this->fid,
                    'startPage' => $this->startPage
                ])
                ->lockForUpdate()->first();
            if ($currentCrawlingForum != null) { // might already marked as finished by other concurrency queues
                $currentCrawlingForum->fill([
                    'queueTiming' => $queueTiming->getTiming()
                ] + $threadsCrawler->getProfiles())->save();
                $currentCrawlingForum->delete(); // release current crawl queue lock
            }

            // dispatch next page range crawler if there's un-crawled pages
            if ($threadsCrawler->endPage < ($this->endPage ?? $this->startPage)) {
                $newCrawlerStartPage = $threadsCrawler->endPage + 1;
                CrawlingPostModel::insert([
                    'type' => 'thread',
                    'fid' => $this->fid,
                    'startPage' => $newCrawlerStartPage,
                    'startTime' => microtime(true)
                ]); // lock for next page range reply crawler
                ThreadQueue::dispatch($this->fid, $this->forumName, $newCrawlerStartPage, $this->endPage)->onQueue('crawler');
            }
        });

        \Log::channel('crawler-info')->info('Thread crawler queue completed after ' . ($queueTiming->getTiming()));
    }
}
