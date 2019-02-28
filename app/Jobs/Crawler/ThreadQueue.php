<?php

namespace App\Jobs\Crawler;

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

class ThreadQueue extends CrawlerQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $queueStartTime;

    protected $forumID;

    private $forumName;

    public function __construct(int $forumID, string $forumName)
    {
        Log::info("Thread crawler queue constructed with {$forumName}");
        $this->forumID = $forumID;
        $this->forumName = $forumName;
    }

    public function handle()
    {
        $this->queueStartTime = microtime(true);
        \DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'); // change present crawler queue session's transaction isolation level to reduce deadlock

        \DB::transaction(function () {
            $crawlingForumInfo = [
                'type' => 'thread',
                'fid' => $this->forumID,
                'tid' => 0
            ];
            $latestCrawlingForum = CrawlingPostModel::select('id', 'startTime')
                ->type('thread')->where($crawlingForumInfo)->lockForUpdate()->first(); // not including reply and sub reply crawler queue
            if ($latestCrawlingForum != null) { // is latest crawler existed and started before $queueDeleteAfter ago
                if ($latestCrawlingForum->startTime < new Carbon($this->queueDeleteAfter)) {
                    $latestCrawlingForum->delete();
                } else {
                    return; // exit queue
                }
            }
            CrawlingPostModel::insert($crawlingForumInfo + ['startTime' => microtime(true)]); // report crawling threads
        });

        $threadsCrawler = (new Crawler\ThreadCrawler($this->forumID, $this->forumName))->doCrawl();
        $newThreadsInfo = $threadsCrawler->getThreadsInfo();
        $oldThreadsInfo = static::convertIDListKey(
            PostModelFactory::newThread($this->forumID)
                ->select('tid', 'latestReplyTime', 'replyNum')
                ->whereIn('tid', array_keys($newThreadsInfo))->get()->toArray(),
            'tid'
        );
        $threadsCrawler->saveLists();

        \DB::transaction(function () use ($newThreadsInfo, $oldThreadsInfo) {
            // including sub reply to prevent repeat crawling sub reply's parent reply
            $previousCrawlingSubPosts = CrawlingPostModel::select('id', 'tid', 'startTime')
                ->type(['reply', 'subReply'])->whereIn('tid', array_keys($newThreadsInfo))->lockForUpdate()->get();
            foreach ($newThreadsInfo as $tid => $newThread) {
                foreach ($previousCrawlingSubPosts as $previousCrawlingSubPost) {
                    if ($previousCrawlingSubPost->tid == $tid // is latest crawler existed and started before $queueDeleteAfter ago
                        || $previousCrawlingSubPost->startTime < new Carbon($this->queueDeleteAfter)) {
                        $previousCrawlingSubPost->delete();
                    } else {
                        continue 2; // skip current pending thread's sub post crawl
                    }
                }
                if ((! isset($oldThreadsInfo[$tid])) // do we have to crawl new replies under thread
                    || (strtotime($newThread['latestReplyTime']) != strtotime($oldThreadsInfo[$tid]['latestReplyTime']))
                    || ($newThread['replyNum'] != $oldThreadsInfo[$tid]['replyNum'])) {
                    CrawlingPostModel::insert([
                        'type' => 'reply',
                        'fid' => $this->forumID,
                        'tid' => $tid,
                        'startTime' => microtime(true)
                    ]); // report crawling replies
                    ReplyQueue::dispatch($this->forumID, $tid)->onQueue('crawler');
                }
            }
        });

        $queueFinishTime = microtime(true);
        \DB::transaction(function () use ($queueFinishTime, $threadsCrawler) {
            // report previous finished forum crawl
            $currentCrawlingForum = CrawlingPostModel::select('id', 'startTime')->where([
                'type' => 'thread', // not including reply and sub reply crawler queue
                'fid' => $this->forumID,
                'tid' => 0
            ])->first();
            if ($currentCrawlingForum != null) { // might already marked as finished by other concurrency queues
                $currentCrawlingForum->fill([
                    'duration' => $queueFinishTime - $this->queueStartTime
                ] + $threadsCrawler->getTimes())->save();
                $currentCrawlingForum->delete();
            }
        });
        Log::info('Thread crawler queue completed after ' . ($queueFinishTime - $this->queueStartTime));
    }
}
