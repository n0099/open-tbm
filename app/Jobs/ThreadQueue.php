<?php

namespace App\Jobs;

use App\Eloquent\CrawlingPostModel;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Tieba\Crawler;
use App\Tieba\Eloquent;

class ThreadQueue extends CrawlerQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $forumId;

    private $forumName;

    private $queuePushTime;

    public function __construct(int $forumId, string $forumName)
    {
        Log::info('thread queue constructed with' . $forumName);
        $this->forumId = $forumId;
        $this->forumName = $forumName;
        $this->queuePushTime = microtime(true);
    }

    public function handle()
    {
        $queueStartTime = microtime(true);
        Log::info('thread queue start after waiting for ' . ($queueStartTime - $this->queuePushTime));

        \DB::beginTransaction();
        $crawlingForumInfo = ['fid' => $this->forumId, 'tid' => 0];
        $latestCrawlingForum = CrawlingPostModel::select('id', 'startTime')->where($crawlingForumInfo)->lockForUpdate()->first();
        if ($latestCrawlingForum != null) { // is latest crawler existed and started before $queueDeleteAfter ago
            if ($latestCrawlingForum->startTime < new Carbon($this->queueDeleteAfter)) {
                $latestCrawlingForum->delete();
            } else {
                return; // exit queue
            }
        }
        CrawlingPostModel::insert($crawlingForumInfo + ['startTime' => microtime(true)]); // report crawling threads
        \DB::commit();

        $threadsCrawler = (new Crawler\ThreadCrawler($this->forumId, $this->forumName))->doCrawl();
        $newThreadsInfo = $threadsCrawler->getThreadsInfo();
        $oldThreadsInfo = self::convertIDListKey(Eloquent\ModelFactory::newThread($this->forumId)
            ->select('tid', 'latestReplyTime', 'replyNum')
            ->whereIn('tid', array_keys($newThreadsInfo))->get()->toArray(), 'tid');
        $threadsCrawler->saveLists();
        echo 'thread:' . memory_get_usage() . PHP_EOL;

        \DB::transaction(function () use ($newThreadsInfo, $oldThreadsInfo) {
            $latestCrawlingThreads = CrawlingPostModel::select('id', 'tid', 'startTime')
                ->whereIn('tid', array_keys($newThreadsInfo))->lockForUpdate()->get();
            $latestCrawlingThreadsID = array_column($latestCrawlingThreads->toArray(), 'tid');
            foreach ($newThreadsInfo as $tid => $newThread) {
                if (array_search($tid, $latestCrawlingThreadsID) === true) { // is latest crawler existed and started before $queueDeleteAfter ago
                    if ($latestCrawlingThreads->startTime < new Carbon($this->queueDeleteAfter)) {
                        $latestCrawlingThreads->delete();
                    } else {
                        continue;
                    }
                }
                if ((! isset($oldThreadsInfo[$tid]))
                    || (strtotime($newThread['latestReplyTime']) != strtotime($oldThreadsInfo[$tid]['latestReplyTime']))
                    || ($newThread['replyNum'] != $oldThreadsInfo[$tid]['replyNum'])) {
                    CrawlingPostModel::insert([
                        'fid' => $this->forumId,
                        'tid' => $tid,
                        'startTime' => microtime(true)
                    ]); // report crawling replies
                    ReplyQueue::dispatch($this->forumId, $tid);
                }
            }
        });

        $queueFinishTime = microtime(true);
        $currentCrawlingThread = CrawlingPostModel::select('id', 'startTime')->where(['fid' => $this->forumId, 'tid' => 0])->first();
        $currentCrawlingThread->fill(['duration' => $queueFinishTime - $currentCrawlingThread->startTime])->save();
        $currentCrawlingThread->delete();
        Log::info('thread queue handled after ' . ($queueFinishTime - $queueStartTime));
    }
}
