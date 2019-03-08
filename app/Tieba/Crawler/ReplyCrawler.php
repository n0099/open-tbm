<?php

namespace App\Tieba\Crawler;

use App\Eloquent\IndexModel;
use App\Exceptions\ExceptionAdditionInfo;
use App\Helper;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\TiebaException;
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

    protected $webRequestTimes = 0;

    protected $parsedPostTimes = 0;

    protected $parsedUserTimes = 0;

    public function doCrawl(): self
    {
        $client = $this->getClientHelper();

        Log::info("Start to fetch replies for tid {$this->threadID}, page 1");
        $repliesJson = json_decode($client->post(
            'http://c.tieba.baidu.com/c/f/pb/page',
            ['form_params' => ['kz' => $this->threadID, 'pn' => 1]] // reverse order will be ['last' => 1,'r' => 1]
        )->getBody(), true);
        $this->webRequestTimes += 1;

        try {
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
                        $this->webRequestTimes += 1;
                        $repliesJson = json_decode($response->getBody(), true);
                        $this->parseRepliesList($repliesJson);
                    },
                    'rejected' => function (GuzzleHttp\Exception\RequestException $e, int $index) {
                        report($e);
                    }
                ]
            ))->promise()->wait();
        } catch (TiebaException $regularException) {
            \Log::warning($regularException->getMessage() . ' ' . ExceptionAdditionInfo::format());
        } catch (\Exception $e) {
            report($e);
        }

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
        switch ($repliesJson['error_code']) {
            case 0:
                $repliesList = $repliesJson['post_list'];
                $usersList = [];
                // inherits by reference to sync value changes
                array_map(function ($userInfo) use (&$usersList) {
                    $usersList[$userInfo['id']] = $userInfo;
                }, $repliesJson['user_list']);
                break;
            case 4: // {"error_code": "4", "error_msg": "贴子可能已被删除"}
                throw new TiebaException('Thread already deleted when crawling reply.');
            default:
                throw new \RuntimeException('Error from tieba client when crawling reply, raw json: ' . json_encode($repliesJson));
        }
        if (count($repliesList) == 0) {
            throw new TiebaException('Reply list is empty, posts might already deleted from tieba.');
        }

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
                'content' => static::nullableValidate($reply['content'], true),
                'authorUid' => $reply['author_id'],
                'authorManagerType' => static::nullableValidate($usersList[$reply['author_id']]['bawu_type'] ?? null), // might be null for unknown reason
                'authorExpGrade' => static::nullableValidate($usersList[$reply['author_id']]['level_id'] ?? null), // might be null for unknown reason
                'subReplyNum' => $reply['sub_post_number'],
                'postTime' => Carbon::createFromTimestamp($reply['time'])->toDateTimeString(),
                'isFold' => $reply['is_fold'],
                'agreeInfo' => static::nullableValidate(($reply['agree']['has_agree'] > 0 ? $reply['agree'] : null), true),
                'signInfo' => static::nullableValidate($reply['signature'], true),
                'tailInfo' => static::nullableValidate($reply['tail_info'], true),
                'clientVersion' => $this->clientVersion,
                'created_at' => $now,
                'updated_at' => $now
            ];

            $this->parsedPostTimes += 1;
            $latestInfo = end($repliesInfo);
            if ($reply['sub_post_number'] > 0) {
                $repliesUpdateInfo[$reply['id']] = static::getArrayValuesByKeys($latestInfo, ['subReplyNum']);
            }
            $indexesInfo[] = [
                'created_at' => $now,
                'updated_at' => $now,
                'postTime' => $latestInfo['postTime'],
                'type' => 'reply',
                'fid' => $this->forumID
            ] + Helper::getArrayValuesByKeys($latestInfo, ['tid', 'pid', 'authorUid']);
        }
        ExceptionAdditionInfo::remove('parsingPid');

        // lazy saving to Eloquent model
        $this->parsedUserTimes = $this->usersInfo->parseUsersList($usersList);
        $this->repliesUpdateInfo = $repliesUpdateInfo + $this->repliesUpdateInfo;
        $this->repliesList = array_merge($this->repliesList, $repliesInfo);
        $this->indexesList = array_merge($this->indexesList, $indexesInfo);
    }

    public function saveLists(): self
    {
        if ($this->indexesList != null) { // if TiebaException thrown while parsing posts, indexes list might be null
            \DB::transaction(function () {
                ExceptionAdditionInfo::set(['insertingReplies' => true]);
                $chunkInsertBufferSize = 2000;
                $replyModel = PostModelFactory::newReply($this->forumID);
                foreach (static::groupNullableColumnArray($this->repliesList, [
                    'authorManagerType',
                    'authorExpGrade'
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
        }

        ExceptionAdditionInfo::remove('crawlingFid', 'crawlingTid');
        $this->repliesList = [];
        $this->indexesList = [];
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
            'crawlingTid' => $tid,
            'webRequestTimes' => &$this->webRequestTimes, // assign by reference will sync values change with addition info
            'parsedPostTimes' => &$this->parsedPostTimes,
            'parsedUserTimes' => &$this->parsedUserTimes
        ]);
    }
}
