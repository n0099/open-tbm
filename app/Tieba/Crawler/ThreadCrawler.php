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

    protected $forumName;

    protected $threadsList = [];

    protected $indexesList = [];

    protected $threadsUpdateInfo = [];

    public function doCrawl() : self
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

    private function parseThreadsList(array $threadsJson)
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
            $usersList[] = $thread['author'] + ['gender' => $thread['author']['sex']]; // sb 6.0.2
            $threadsInfo[] = [
                'tid' => $thread['tid'],
                'firstPid' => $thread['first_post_id'],
                'isSticky' => $thread['is_top'],
                'isGood' => $thread['is_good'],
                'title' => $thread['title'],
                'authorUid' => $thread['author']['id'],
                'authorManagerType' => self::valueValidate($thread['author']['bawu_type']),
                'postTime' => Carbon::createFromTimestamp($thread['create_time'])->toDateTimeString(),
                'latestReplyTime' => Carbon::createFromTimestamp($thread['last_time_int'])->toDateTimeString(),
                'latestReplierUid' => $thread['last_replyer']['id'],
                'replyNum' => $thread['reply_num'],
                'viewNum' => $thread['view_num'],
                'shareNum' => $thread['share_num'],
                'agreeInfo' => self::valueValidate(($thread['agree']['has_agree'] > 0 ? $thread['agree'] : null), true),
                'zanInfo' => self::valueValidate($thread['zan'], true),
                'locationInfo' => self::valueValidate($thread['location'], true),
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
                'fid' => $this->forumId
            ] + self::getSubKeyValueByKeys($latestInfo, ['tid', 'authorUid', 'postTime']);
        }

        // Lazy saving to Eloquent model
        $this->parseUsersList(collect($usersList)->unique('id')->toArray());
        $this->threadsUpdateInfo = $threadsUpdateInfo;
        $this->threadsList = $threadsInfo;
        $this->indexesList = $indexesInfo;
    }

    public function saveLists() : self
    {
        $threadExceptFields = array_diff(array_keys($this->threadsList[0]), [
            'tid',
            'title',
            'postTime',
            'authorUid',
            'created_at'
        ]);
        Eloquent\ModelFactory::newThread($this->forumId)->insertOnDuplicateKey($this->threadsList, $threadExceptFields);
        $indexExceptFields = array_diff(array_keys($this->indexesList[0]), ['created_at']);
        (new \App\Eloquent\IndexModel())->insertOnDuplicateKey($this->indexesList, $indexExceptFields);
        $this->saveUsersList();

        return $this;
    }

    public function getThreadsInfo() : array
    {
        return $this->threadsUpdateInfo;
    }

    public function __construct(int $forumId, string $forumName)
    {
        $this->forumId = $forumId;
        $this->forumName = $forumName;
    }
}
