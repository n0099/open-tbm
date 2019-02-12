<?php

namespace App\Tieba\Crawler;

use App\Exceptions\ExceptionAdditionInfo;
use App\Tieba\Eloquent;
use Carbon\Carbon;
use GuzzleHttp;
use Illuminate\Support\Facades\Log;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;

class SubReplyCrawler extends Crawlable
{
    protected $clientVersion = '8.8.8';

    protected $forumID;

    protected $threadID;

    protected $replyID;

    protected $usersInfo;

    protected $indexesList = [];

    protected $subRepliesList = [];

    public function doCrawl(): self
    {
        $client = $this->getClientHelper();

        Log::info("Start to fetch sub replies for pid {$this->replyID}, tid {$this->threadID}, page 1");
        $subRepliesJson = json_decode($client->post(
            'http://c.tieba.baidu.com/c/f/pb/floor',
            ['form_params' => ['kz' => $this->threadID, 'pid' => $this->replyID, 'pn' => 1]]
        )->getBody(), true);

        $this->parseSubRepliesList($subRepliesJson);

        (new GuzzleHttp\Pool(
            $client,
            (function () use ($client, $subRepliesJson) {
                for ($pn = 2; $pn <= $subRepliesJson['page']['total_page']; $pn++) {
                    yield function () use ($client, $pn) {
                        Log::info("Fetch sub replies for pid {$this->replyID}, tid {$this->threadID}, page {$pn}");
                        return $client->postAsync(
                            'http://c.tieba.baidu.com/c/f/pb/floor',
                            ['form_params' => ['kz' => $this->threadID, 'pid' => $this->replyID, 'pn' => $pn]]
                        );
                    };
                }
            })(),
            [
                'concurrency' => 10,
                'fulfilled' => function (\Psr\Http\Message\ResponseInterface $response, int $index) {
                    $subRepliesJson = json_decode($response->getBody(), true);
                    $this->parseSubRepliesList($subRepliesJson);
                },
                'rejected' => function (GuzzleHttp\Exception\RequestException $e, int $index) {
                    report($e);
                }
            ]
        ))->promise()->wait();

        return $this;
    }

    private function parseSubRepliesList(array $subRepliesJson): void
    {
        if ($subRepliesJson['error_code'] == 0) {
            $subRepliesList = $subRepliesJson['subpost_list'];
        } else {
            throw new \RuntimeException("Error from tieba client, raw json: " . json_encode($subRepliesJson));
        }
        if (count($subRepliesList) == 0) {
            throw new \LengthException('Sub reply posts list is empty, posts might already deleted from tieba');
        }

        $usersList = [];
        $subRepliesInfo = [];
        $indexesInfo = [];
        $now = Carbon::now();
        foreach ($subRepliesList as $subReply) {
            ExceptionAdditionInfo::set(['parsingSpid' => $subReply['id']]);
            $usersList[] = $subReply['author'];
            $subRepliesInfo[] = [
                'tid' => $this->threadID,
                'pid' => $this->replyID,
                'spid' => $subReply['id'],
                'content' => self::valueValidate($subReply['content'], true),
                'authorUid' => $subReply['author']['id'],
                'authorManagerType' => self::valueValidate($subReply['author']['bawu_type']),
                'authorExpGrade' => $subReply['author']['level_id'],
                'postTime' => Carbon::createFromTimestamp($subReply['time'])->toDateTimeString(),
                'clientVersion' => $this->clientVersion,
                'created_at' => $now,
                'updated_at' => $now
            ];

            $latestInfo = end($subRepliesInfo);
            $indexesInfo[] = [
                'created_at' => $now,
                'updated_at' => $now,
                'postTime' => $latestInfo['postTime'],
                'type' => 'subReply',
                'fid' => $this->forumID
            ] + self::getArrayValuesByKeys($latestInfo, ['tid', 'pid', 'spid', 'authorUid']);
        }
        ExceptionAdditionInfo::remove('parsingSpid');

        // lazy saving to Eloquent model
        $this->usersInfo->parseUsersList(collect($usersList)->unique('id')->toArray());
        $this->subRepliesList = array_merge($this->subRepliesList, $subRepliesInfo);
        $this->indexesList = array_merge($this->indexesList, $indexesInfo);
    }

    public function saveLists(): self
    {
        \DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED'); // change next transaction's isolation level to reduce deadlock
        \DB::transaction(function () {
            ExceptionAdditionInfo::set(['insertingSubReplies' => true]);
            $chunkInsertBufferSize = 2000;
            $subReplyModel = Eloquent\PostModelFactory::newSubReply($this->forumID);
            $subReplyUpdateFields = array_diff(array_keys($this->subRepliesList[0]), $subReplyModel->updateExpectFields);
            $subReplyModel->chunkInsertOnDuplicate($this->subRepliesList, $subReplyUpdateFields, $chunkInsertBufferSize);

            $indexModel = new \App\Eloquent\IndexModel();
            $indexUpdateFields = array_diff(array_keys($this->indexesList[0]), $indexModel->updateExpectFields);
            $indexModel->chunkInsertOnDuplicate($this->indexesList, $indexUpdateFields, $chunkInsertBufferSize);
            ExceptionAdditionInfo::remove('insertingSubReplies');

            $this->usersInfo->saveUsersList();
        });

        ExceptionAdditionInfo::remove('crawlingFid', 'crawlingTid', 'crawlingPid');
        return $this;
    }

    public function __construct(int $fid, int $tid, int $pid)
    {
        $this->forumID = $fid;
        $this->threadID = $tid;
        $this->replyID = $pid;
        $this->usersInfo = new UserInfoParser();

        ExceptionAdditionInfo::set([
            'crawlingFid' => $fid,
            'crawlingTid' => $tid,
            'crawlingPid' => $pid
        ]);
    }
}
