<?php

namespace App\Tieba\Crawler;

use App\Tieba\Eloquent;
use Carbon\Carbon;
use GuzzleHttp;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;
use Illuminate\Support\Facades\Log;

class ThreadCrawler extends Crawlable
{
    protected $clientVersion = '6.0.2';

    protected $forumID;

    protected $forumName;

    protected $threadsList = [];

    protected $indexesList = [];

    protected $threadsUpdateInfo = [];

    public function doCrawl(): self
    {
        $client = $this->getClientHelper();

        Log::info("Start to fetch threads for forum {$this->forumName}, page 1");
        $threadsList = json_decode($client->post(
            'http://c.tieba.baidu.com/c/f/frs/page',
            ['form_params' => ['kw' => $this->forumName, 'pn' => 1, 'rn' => 50]]
        )->getBody(), true);

        $this->parseThreadsList($threadsList);

        return $this;
    }

    private function parseThreadsList(array $threadsJson): void
    {
        if ($threadsJson['error_code'] == 0) {
            $threadsList = $threadsJson['thread_list'];
        } else {
            throw new \RuntimeException("Error from tieba client, raw json: " . json_encode($threadsJson));
        }
        if (count($threadsList) == 0) {
            throw new \LengthException('Forum posts list is empty');
        }

        $usersList = [];
        $threadsUpdateInfo = [];
        $threadsInfo = [];
        $indexesInfo = [];
        $now = Carbon::now();
        foreach ($threadsList as $thread) {
            $usersList[] = $thread['author'] + ['gender' => $thread['author']['sex'] ?? null]; // sb 6.0.2
            $threadsInfo[] = [
                'tid' => $thread['tid'],
                'firstPid' => $thread['first_post_id'],
                'stickyType' => $thread['is_membertop'] == 1
                    ? 'membertop'
                    : isset($thread['is_top']) // in 6.0.2 client version, if there's a vip sticky thread and three normal sticky threads, the first(oldest) thread won't have is_top field
                        ? $thread['is_top'] == 0
                            ? null
                            : 'top'
                        : null,
                'isGood' => $thread['is_good'],
                "topicType" => isset($thread['is_livepost']) ? $thread['live_post_type'] : null,
                'title' => $thread['title'],
                'authorUid' => $thread['author']['id'],
                'authorManagerType' => self::valueValidate($thread['author']['bawu_type']),
                'postTime' => isset($thread['create_time']) ? Carbon::createFromTimestamp($thread['create_time'])->toDateTimeString() : null, // post time will be null when it's topic thread
                'latestReplyTime' => Carbon::createFromTimestamp($thread['last_time_int'])->toDateTimeString(),
                'latestReplierUid' => $thread['last_replyer']['id'] ?? null, // topic thread won't have latest replier field
                'replyNum' => $thread['reply_num'],
                'viewNum' => $thread['view_num'],
                'shareNum' => $thread['share_num'] ?? null, // will cover previous shareNum when it's topic thread
                'agreeInfo' => self::valueValidate(isset($thread['agree']) ? ($thread['agree']['has_agree'] > 0 ? $thread['agree'] : null) : null, true),
                'zanInfo' => self::valueValidate($thread['zan'] ?? null, true),
                'locationInfo' => self::valueValidate($thread['location'] ?? null, true),
                'clientVersion' => $this->clientVersion,
                'created_at' => $now,
                'updated_at' => $now
            ];

            $latestInfo = end($threadsInfo);
            $threadsUpdateInfo[$thread['tid']] = self::getSubKeyValueByKeys($latestInfo, ['latestReplyTime', 'replyNum']);
            $indexesInfo[] = [
                'created_at' => $now,
                'updated_at' => $now,
                'type' => 'thread',
                'fid' => $this->forumID
            ] + self::getSubKeyValueByKeys($latestInfo, ['tid', 'authorUid', 'postTime']);
        }

        // lazy saving to Eloquent model
        $this->parseUsersList(collect($usersList)->unique('id')->toArray());
        $this->threadsUpdateInfo = $threadsUpdateInfo;
        $this->threadsList = $threadsInfo;
        $this->indexesList = $indexesInfo;
    }

    public function saveLists(): self
    {
        \DB::transaction(function () {
            $threadExceptFields = array_diff(array_keys($this->threadsList[0]), [
                'tid',
                'title',
                'postTime',
                'authorUid',
                'created_at'
            ]);
            Eloquent\PostModelFactory::newThread($this->forumID)->insertOnDuplicateKey($this->threadsList, $threadExceptFields);
            $indexExceptFields = array_diff(array_keys($this->indexesList[0]), ['created_at']);
            (new \App\Eloquent\IndexModel())->insertOnDuplicateKey($this->indexesList, $indexExceptFields);
        });
        $this->saveUsersList();

        return $this;
    }

    public function getThreadsInfo(): array
    {
        return $this->threadsUpdateInfo;
    }

    public function __construct(int $forumID, string $forumName)
    {
        $this->forumID = $forumID;
        $this->forumName = $forumName;
    }
}
