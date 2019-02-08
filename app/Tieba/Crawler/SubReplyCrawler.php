<?php

namespace App\Tieba\Crawler;

use App\Tieba\Eloquent;
use Carbon\Carbon;
use GuzzleHttp;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;
use Illuminate\Support\Facades\Log;

class SubReplyCrawler extends Crawlable
{
    protected $clientVersion = '8.8.8';

    protected $threadID;

    protected $replyID;

    protected $subRepliesList = [];

    protected $indexesList = [];

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
                    //add_measure($response->getReasonPhrase(), microtime(true), microtime(true));
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
            ] + self::getSubKeyValueByKeys($latestInfo, ['tid', 'pid', 'spid', 'authorUid']);
        }

        // lazy saving to Eloquent model
        $this->parseUsersList(collect($usersList)->unique('id')->toArray());
        $this->subRepliesList = array_merge($this->subRepliesList, $subRepliesInfo);
        $this->indexesList = array_merge($this->indexesList, $indexesInfo);
    }

    public function saveLists(): self
    {
        \DB::transaction(function () {
            $subReplyExceptFields = array_diff(array_keys($this->subRepliesList[0]), [
                'tid',
                'pid',
                'spid',
                'postTime',
                'authorUid',
                'created_at'
            ]);
            Eloquent\PostModelFactory::newSubReply($this->forumID)->insertOnDuplicateKey($this->subRepliesList, $subReplyExceptFields);
            $indexExceptFields = array_diff(array_keys($this->indexesList[0]), ['created_at']);
            (new \App\Eloquent\IndexModel())->insertOnDuplicateKey($this->indexesList, $indexExceptFields);
            $this->saveUsersList();
        });

        return $this;
    }

    public function __construct(int $fid, int $tid, int $pid)
    {
        $this->forumID = $fid;
        $this->threadID = $tid;
        $this->replyID = $pid;
    }
}
