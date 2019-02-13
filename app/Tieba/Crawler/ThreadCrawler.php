<?php

namespace App\Tieba\Crawler;

use App\Eloquent\IndexModel;
use App\Exceptions\ExceptionAdditionInfo;
use App\Tieba\Eloquent\PostModelFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;

class ThreadCrawler extends Crawlable
{
    protected $clientVersion = '6.0.2';

    protected $forumID;

    protected $forumName;

    protected $usersInfo;

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
            ExceptionAdditionInfo::set(['parsingTid' => $thread['tid']]);
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
                'authorManagerType' => static::valueValidate($thread['author']['bawu_type'] ?? null), // topic thread won't have this
                'postTime' => isset($thread['create_time']) ? Carbon::createFromTimestamp($thread['create_time'])->toDateTimeString() : null, // topic thread won't have this
                'latestReplyTime' => Carbon::createFromTimestamp($thread['last_time_int'])->toDateTimeString(),
                'latestReplierUid' => $thread['last_replyer']['id'] ?? null, // topic thread won't have this
                'replyNum' => $thread['reply_num'],
                'viewNum' => $thread['view_num'],
                'shareNum' => $thread['share_num'] ?? null, // topic thread won't have this
                'agreeInfo' => static::valueValidate(isset($thread['agree']) ? ($thread['agree']['has_agree'] > 0 ? $thread['agree'] : null) : null, true), // topic thread won't have this
                'zanInfo' => static::valueValidate($thread['zan'] ?? null, true),
                'locationInfo' => static::valueValidate($thread['location'] ?? null, true),
                'clientVersion' => $this->clientVersion,
                'created_at' => $now,
                'updated_at' => $now
            ];

            $latestInfo = end($threadsInfo);
            $threadsUpdateInfo[$thread['tid']] = static::getArrayValuesByKeys($latestInfo, ['latestReplyTime', 'replyNum']);
            $indexesInfo[] = [
                'created_at' => $now,
                'updated_at' => $now,
                'type' => 'thread',
                'fid' => $this->forumID
            ] + static::getArrayValuesByKeys($latestInfo, ['tid', 'authorUid', 'postTime']);
        }
        ExceptionAdditionInfo::remove('parsingTid');

        // lazy saving to Eloquent model
        $this->usersInfo->parseUsersList(collect($usersList)->unique('id')->toArray());
        $this->threadsUpdateInfo = $threadsUpdateInfo;
        $this->threadsList = $threadsInfo;
        $this->indexesList = $indexesInfo;
    }

    public function saveLists(): self
    {
        \DB::transaction(function () {
            ExceptionAdditionInfo::set(['insertingThreads' => true]);
            $chunkInsertBufferSize = 2000;
            $threadModel = PostModelFactory::newThread($this->forumID);
            foreach (static::groupNullableColumnArray($this->threadsList, [
                'postTime',
                'latestReplyTime',
                'latestReplierUid',
                'shareNum',
                'agreeInfo'
            ]) as $threadsListGroup) {
                $threadUpdateFields = array_diff(array_keys($threadsListGroup[0]), $threadModel->updateExpectFields);
                $threadModel->chunkInsertOnDuplicate($threadsListGroup, $threadUpdateFields, $chunkInsertBufferSize);
            }

            $indexModel = new IndexModel();
            $indexUpdateFields = array_diff(array_keys($this->indexesList[0]), $indexModel->updateExpectFields);
            $indexModel->chunkInsertOnDuplicate($this->indexesList, $indexUpdateFields, $chunkInsertBufferSize);
            ExceptionAdditionInfo::remove('insertingThreads');

            $this->usersInfo->saveUsersList();
        });

        ExceptionAdditionInfo::remove('crawlingFid', 'crawlingForumName');
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
        $this->usersInfo = new UserInfoParser();

        ExceptionAdditionInfo::set([
            'crawlingFid' => $forumID,
            'crawlingForumName' => $forumName
        ]);
    }
}
