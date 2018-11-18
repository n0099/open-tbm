<?php

namespace App\Tieba\Crawler;

use App\Tieba\Eloquent;
use Carbon\Carbon;
use GuzzleHttp;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;

class ReplyCrawler extends Crawlable
{
    protected $threadId;

    protected $clientVersion = '8.8.8';

    protected $containsSubReplyPid = [];

    public function doCrawl() : self
    {
        $client = $this->getClientHelper();

        $repliesJson = json_decode($client->post(
            'http://c.tieba.baidu.com/c/f/pb/page',
            ['form_params' => ['kz' => $this->threadId, 'pn' => 1]] // reverse order = ['last'=>1,'r'=>1]
        )->getBody(), true);

        $this->parseRepliesList($repliesJson);

        (new GuzzleHttp\Pool(
            $client,
            (function () use ($client, $repliesJson) {
                for ($pn = 2; $pn <= $repliesJson['page']['total_page']; $pn++) {
                    yield function () use ($client, $pn) {
                        return $client->postAsync(
                            'http://c.tieba.baidu.com/c/f/pb/page',
                            ['form_params' => ['kz' => $this->threadId, 'pn' => $pn]]
                        );
                    };
                }
            })(),
            [
                'concurrency' => 20,
                'fulfilled' => function (\Psr\Http\Message\ResponseInterface $response, int $index) {
                    //add_measure($response->getReasonPhrase(), microtime(true), microtime(true));
                    $repliesJson = json_decode($response->getBody(), true);
                    $this->parseRepliesList($repliesJson);
                },
                'rejected' => function (GuzzleHttp\Exception\RequestException $e, int $index) {
                    report($e);
                }
            ]
        ))->promise()->wait();

        return $this;
    }

    private static function convertUsersListToUidKey(array $usersList) : array
    {
        $newUsersList = [];

        foreach ($usersList as $user) {
            $uid = $user['id'];
            $newUsersList[$uid] = $user;
        }

        return $newUsersList;
    }

    private function parseRepliesList(array $repliesJson)
    {
        if ($repliesJson['error_code'] == 0) {
            $repliesList = $repliesJson['post_list'];
            $usersList = $repliesJson['user_list'];
        } else {
            throw new \HttpResponseException("Error from tieba client, raw json: " . json_encode($repliesJson));
        }
        if (count($repliesList) == 0) {
            throw new \LengthException('Reply posts list is empty');
        }

        $usersList = self::convertUsersListToUidKey($usersList);
        $repliesInfo = [];
        $indexesInfo = [];
        $subReplyPid = [];
        foreach ($repliesList as $reply) {
            $repliesInfo[] = [
                'tid' => $this->threadId,
                'pid' => $reply['id'],
                'floor' => $reply['floor'],
                'content' => self::valueValidate($reply['content'], true),
                'authorUid' => $reply['author_id'],
                'authorManagerType' => self::valueValidate($usersList[$reply['author_id']]['bawu_type']),
                'authorExpGrade' => $usersList[$reply['author_id']]['level_id'],
                'subReplyNum' => $reply['sub_post_number'],
                'replyTime' => Carbon::createFromTimestamp($reply['time'])->toDateTimeString(),
                'isFold' => $reply['is_fold'],
                'agreeInfo' => self::valueValidate(($reply['agree']['has_agree'] > 0 ? $reply['agree'] : null), true),
                'signInfo' => self::valueValidate($reply['signature'], true),
                'tailInfo' => self::valueValidate($reply['tail_info'], true),
                'clientVersion' => $this->clientVersion,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
            if ($reply['sub_post_number'] > 0) {
                $subReplyPid[] = $reply['id'];
            }
            $indexesInfo[] = [
                'fid' => $this->forumId,
                'type' => 'reply',
                'tid' => $this->threadId,
                'pid' => end($repliesInfo)['pid'],
                'authorUid' => end($repliesInfo)['authorUid'],
                'postTime' => end($repliesInfo)['replyTime']
            ];
        }
        $replyUpdateExceptFields = array_diff(array_keys($repliesInfo[0]), [
            'tid',
            'pid',
            'floor',
            'replyTime',
            'authorUid',
            'created_at'
        ]);

        $this->containsSubReplyPid = $subReplyPid;
        $this->parseUsersList($usersList);
        Eloquent\ModelFactory::newReply($this->forumId)->insertOnDuplicateKey($repliesInfo, $replyUpdateExceptFields);
        (new Eloquent\IndexModel())->insertOnDuplicateKey($indexesInfo);
    }

    public function getPidContainsSubReply() : array
    {
        return $this->containsSubReplyPid;
    }

    public function __construct(int $tid, int $fid)
    {
        $this->threadId = $tid;
        $this->forumId = $fid;
    }
}
