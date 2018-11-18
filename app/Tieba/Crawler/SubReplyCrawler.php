<?php

namespace App\Tieba\Crawler;

use App\Tieba\Eloquent;
use Carbon\Carbon;
use GuzzleHttp;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;

class SubReplyCrawler extends Crawlable
{
    protected $forumId;

    protected $threadId;

    protected $replyId;

    protected $clientVersion = '9.8.8';

    public function doCrawl() : self
    {
        $client = $this->getClientHelper();

        $subRepliesJson = json_decode($client->post(
            'http://c.tieba.baidu.com/c/f/pb/floor',
            ['form_params' => ['kz' => $this->threadId, 'pid' => $this->replyId, 'pn' => 1]]
        )->getBody(), true);

        $this->parseSubRepliesList($subRepliesJson);

        (new GuzzleHttp\Pool(
            $client,
            (function () use ($client, $subRepliesJson) {
                for ($pn = 2; $pn <= $subRepliesJson['page']['total_page']; $pn++) {
                    yield function () use ($client, $pn) {
                        return $client->postAsync(
                            'http://c.tieba.baidu.com/c/f/pb/floor',
                            ['form_params' => ['kz' => $this->threadId, 'pid' => $this->replyId, 'pn' => $pn]]
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

    private function parseSubRepliesList(array $subRepliesJson)
    {
        if ($subRepliesJson['error_code'] == 0) {
            $subRepliesList = $subRepliesJson['subpost_list'];
        } else {
            throw new \HttpResponseException("Error from tieba client, raw json: " . json_encode($subRepliesJson));
        }
        if (count($subRepliesList) == 0) {
            throw new \LengthException('Sub reply posts list is empty');
        }

        $usersList = [];
        $subRepliesInfo = [];
        $indexesInfo = [];
        foreach ($subRepliesList as $subReply) {
            $usersList[] = $subReply['author'];
            $subRepliesInfo[] = [
                'tid' => $this->threadId,
                'pid' => $this->replyId,
                'spid' => $subReply['id'],
                'content' => self::valueValidate($subReply['content'], true),
                'authorUid' => $subReply['author']['id'],
                'authorManagerType' => self::valueValidate($subReply['author']['bawu_type']),
                'authorExpGrade' => $subReply['author']['level_id'],
                'replyTime' => Carbon::createFromTimestamp($subReply['time'])->toDateTimeString(),
                'clientVersion' => $this->clientVersion,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
            $indexesInfo[] = [
                'fid' => $this->forumId,
                'type' => 'subReply',
                'tid' => $this->threadId,
                'pid' => $this->replyId,
                'spid' => end($subRepliesInfo)['spid'],
                'authorUid' => end($subRepliesInfo)['authorUid'],
                'postTime' => end($subRepliesInfo)['replyTime']
            ];
        }
        $subReplyUpdateExceptFields = array_diff(array_keys($subRepliesInfo[0]), [
            'tid',
            'pid',
            'spid',
            'replyTime',
            'authorUid',
            'created_at'
        ]);

        $this->parseUsersList(collect($usersList)->unique('id')->toArray());
        Eloquent\ModelFactory::newSubReply($this->forumId)->insertOnDuplicateKey($subRepliesInfo, $subReplyUpdateExceptFields);
        (new Eloquent\IndexModel())->insertOnDuplicateKey($indexesInfo);
    }

    public function __construct(int $tid, int $pid, int $fid)
    {
        $this->threadId = $tid;
        $this->replyId = $pid;
        $this->forumId = $fid;
    }
}
