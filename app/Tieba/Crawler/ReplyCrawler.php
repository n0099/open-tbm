<?php

namespace App\Tieba\Crawler;

use App\Eloquent\IndexModel;
use App\Exceptions\ExceptionAdditionInfo;
use App\Tieba\Eloquent;
use Carbon\Carbon;
use GuzzleHttp;
use Illuminate\Support\Facades\Log;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;

class ReplyCrawler extends Crawlable
{
    protected $clientVersion = '8.8.8';

    protected $forumID;

    protected $threadID;

    protected $usersInfo;

    protected $repliesList = [];

    protected $indexesList = [];

    protected $repliesUpdateInfo = [];

    public function doCrawl(): self
    {
        $client = $this->getClientHelper();

        Log::info("Start to fetch replies for tid {$this->threadID}, page 1");
        $repliesJson = json_decode($client->post(
            'http://c.tieba.baidu.com/c/f/pb/page',
            ['form_params' => ['kz' => $this->threadID, 'pn' => 1]] // reverse order will be ['last' => 1,'r' => 1]
        )->getBody(), true);

        $this->parseRepliesList($repliesJson);

        (new GuzzleHttp\Pool(
            $client,
            (function () use ($client, $repliesJson) {
                for ($pn = 2; $pn <= $repliesJson['page']['total_page']; $pn++) {
                    yield function () use ($client, $pn) {
                        Log::info("Start to fetch replies for tid {$this->threadID}, page {$pn}");
                        return $client->postAsync(
                            'http://c.tieba.baidu.com/c/f/pb/page',
                            ['form_params' => ['kz' => $this->threadID, 'pn' => $pn]]
                        );
                    };
                }
            })(),
            [
                'concurrency' => 10,
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

    private static function convertUsersListToUidKey(array $usersList): array
    {
        $newUsersList = [];

        foreach ($usersList as $user) {
            $uid = $user['id'];
            $newUsersList[$uid] = $user;
        }

        return $newUsersList;
    }

    private function parseRepliesList(array $repliesJson): void
    {
        if ($repliesJson['error_code'] == 0) {
            $repliesList = $repliesJson['post_list'];
            $usersList = $repliesJson['user_list'];
        } else {
            throw new \RuntimeException("Error from tieba client, raw json: " . json_encode($repliesJson));
        }
        if (count($repliesList) == 0) {
            throw new \LengthException('Reply posts list is empty, posts might already deleted from tieba');
        }

        $usersList = self::convertUsersListToUidKey($usersList);
        $repliesUpdateInfo = [];
        $repliesInfo = [];
        $indexesInfo = [];
        $now = Carbon::now();
        foreach ($repliesList as $reply) {
            ExceptionAdditionInfo::set(['parsingPid' => $reply['id']]);
            $repliesInfo[] = [
                'tid' => $this->threadID,
                'pid' => $reply['id'],
                'floor' => $reply['floor'],
                'content' => self::valueValidate($reply['content'], true),
                'authorUid' => $reply['author_id'],
                'authorManagerType' => self::valueValidate($usersList[$reply['author_id']]['bawu_type'] ?? null), // might be null for unknown reason
                'authorExpGrade' => $usersList[$reply['author_id']]['level_id'],
                'subReplyNum' => $reply['sub_post_number'],
                'postTime' => Carbon::createFromTimestamp($reply['time'])->toDateTimeString(),
                'isFold' => $reply['is_fold'],
                'agreeInfo' => self::valueValidate(($reply['agree']['has_agree'] > 0 ? $reply['agree'] : null), true),
                'signInfo' => self::valueValidate($reply['signature'], true),
                'tailInfo' => self::valueValidate($reply['tail_info'], true),
                'clientVersion' => $this->clientVersion,
                'created_at' => $now,
                'updated_at' => $now
            ];

            $latestInfo = end($repliesInfo);
            if ($reply['sub_post_number'] > 0) {
                $repliesUpdateInfo[$reply['id']] = self::getArrayValuesByKeys($latestInfo, ['subReplyNum']);
            }
            $indexesInfo[] = [
                'created_at' => $now,
                'updated_at' => $now,
                'postTime' => $latestInfo['postTime'],
                'type' => 'reply',
                'fid' => $this->forumID
            ] + self::getArrayValuesByKeys($latestInfo, ['tid', 'pid', 'authorUid']);
        }
        ExceptionAdditionInfo::remove('parsingPid');

        // lazy saving to Eloquent model
        $this->usersInfo->parseUsersList($usersList);
        $this->repliesUpdateInfo = $repliesUpdateInfo + $this->repliesUpdateInfo;
        $this->repliesList = array_merge($this->repliesList, $repliesInfo);
        $this->indexesList = array_merge($this->indexesList, $indexesInfo);
    }

    public function saveLists(): self
    {
        \DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED'); // change next transaction's isolation level to reduce deadlock
        \DB::transaction(function () {
            ExceptionAdditionInfo::set(['insertingReplies' => true]);
            $chunkInsertBufferSize = 2000;
            $replyModel = Eloquent\PostModelFactory::newReply($this->forumID);
            foreach (self::groupNullableColumnArray($this->repliesList, [
                'authorManagerType'
            ]) as $repliesListGroup) {
                $replyUpdateFields = array_diff(array_keys($repliesListGroup[0]), $replyModel->updateExpectFields);
                $replyModel->chunkInsertOnDuplicate($repliesListGroup, $replyUpdateFields, $chunkInsertBufferSize);
            }

            $indexModel = new IndexModel();
            $indexUpdateFields = array_diff(array_keys($this->indexesList[0]), $indexModel->updateExpectFields);
            $indexModel->chunkInsertOnDuplicate($this->indexesList, $indexUpdateFields, $chunkInsertBufferSize);
            ExceptionAdditionInfo::remove('insertingReplies');

            $this->usersInfo->saveUsersList();
        });

        ExceptionAdditionInfo::remove('crawlingFid', 'crawlingTid');
        return $this;
    }

    public function getRepliesInfo(): array
    {
        return $this->repliesUpdateInfo;
    }

    public function __construct(int $fid, int $tid)
    {
        $this->forumID = $fid;
        $this->threadID = $tid;
        $this->usersInfo = new UserInfoParser();

        ExceptionAdditionInfo::set([
            'crawlingFid' => $fid,
            'crawlingTid' => $tid
        ]);
    }
}
