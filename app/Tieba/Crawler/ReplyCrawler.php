<?php

namespace App\Tieba\Crawler;

use App\Exceptions\ExceptionAdditionInfo;
use App\Helper;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\TiebaException;
use App\Timer;
use Carbon\Carbon;

class ReplyCrawler extends Crawlable
{
    protected string $clientVersion = '8.8.8';

    protected int $tid;

    protected array $repliesInfo = [];

    protected array $updatedPostsInfo = [];

    protected array $parentThreadInfo = [];

    public function __construct(int $fid, int $tid, int $startPage, ?int $endPage = null)
    {
        parent::__construct($fid, $startPage, $endPage, 100); // crawl 100 pages since $startPage by default, rather than thread and sub reply behaviour
        $this->tid = $tid;
        ExceptionAdditionInfo::set(['crawlingTid' => $tid]);
    }

    public function getUpdatedPostsInfo(): array
    {
        return $this->updatedPostsInfo;
    }

    public function doCrawl(): self
    {
        \Log::channel('crawler-info')->info("Start to fetch replies for thread, tid {$this->tid}, page {$this->startPage}");
        ExceptionAdditionInfo::set(['parsingPage' => $this->startPage]);

        $tiebaClient = $this->getClientHelper();
        $webRequestTimer = new Timer();
        $startPageRepliesInfo = json_decode($tiebaClient->post(
            'http://c.tieba.baidu.com/c/f/pb/page',
            [
                'form_params' => [ // reverse order will be ['last' => 1, 'r' => 1]
                    'kz' => $this->tid,
                    'pn' => $this->startPage
                ]
            ]
        )->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->profileWebRequestStopped($webRequestTimer);

        try {
            $this->checkThenParsePostsInfo($startPageRepliesInfo);

            $webRequestTimer->start();
            (new \GuzzleHttp\Pool(
                $tiebaClient,
                (function () use ($tiebaClient) {
                    for ($pn = $this->startPage + 1; $pn <= $this->endPage; $pn++) { // crawling page range [$startPage + 1, $endPage]
                        yield function () use ($tiebaClient, $pn) {
                            \Log::channel('crawler-info')->info("Fetch replies for thread, tid {$this->tid}, page {$pn}");
                            return $tiebaClient->postAsync(
                                'http://c.tieba.baidu.com/c/f/pb/page',
                                [
                                    'form_params' => [
                                        'kz' => $this->tid,
                                        'pn' => $pn
                                    ]
                                ]
                            );
                        };
                    }
                })(),
                $this->getGuzzleHttpPoolConfig($webRequestTimer)
            ))->promise()->wait();
        } catch (TiebaException $regularException) {
            \Log::channel('crawler-notice')->notice($regularException->getMessage() . ' ' . ExceptionAdditionInfo::format());
        } catch (\Exception $e) {
            report($e);
        }

        return $this;
    }

    protected function checkThenParsePostsInfo(array $responseJson): void
    {
        switch ($responseJson['error_code']) {
            case 0: // no error
                break;
            case 4: // {"error_code": "4", "error_msg": "贴子可能已被删除"}
                throw new TiebaException('Thread already deleted when crawling reply');
            default:
                throw new \RuntimeException('Error from tieba client when crawling reply, raw json: ' . json_encode($responseJson, JSON_THROW_ON_ERROR));
        }

        $parentThreadInfo = $responseJson['thread'];
        $repliesList = $responseJson['post_list'];
        $replyUsersList = Helper::setKeyWithItemsValue($responseJson['user_list'], 'id');
        if (\count($repliesList) === 0) {
            throw new TiebaException('Reply list is empty, posts might already deleted from tieba');
        }

        $this->cachePageInfoAndTrimEndPage($responseJson['page']);
        $this->parsePostsInfo($parentThreadInfo, $repliesList, $replyUsersList);
    }

    private function parsePostsInfo(array $parentThreadInfo, array $repliesList, array $usersInfo): void
    {
        $updatedRepliesInfo = [];
        $repliesInfo = [];
        $indexesInfo = [];
        $now = Carbon::now();
        foreach ($repliesList as $reply) {
            ExceptionAdditionInfo::set(['parsingPid' => $reply['id']]);
            $currentInfo = [
                'tid' => $this->tid,
                'pid' => $reply['id'],
                'floor' => $reply['floor'],
                'content' => Helper::nullableValidate($reply['content'], true),
                'authorUid' => $reply['author_id'],
                'authorManagerType' => Helper::nullableValidate($usersInfo[$reply['author_id']]['bawu_type'] ?? null), // might be null for unknown reason
                'authorExpGrade' => Helper::nullableValidate($usersInfo[$reply['author_id']]['level_id'] ?? null), // might be null for unknown reason
                'subReplyNum' => $reply['sub_post_number'],
                'postTime' => Carbon::createFromTimestamp($reply['time'])->toDateTimeString(),
                'isFold' => $reply['is_fold'],
                'location' => Helper::nullableValidate($reply['lbs_info'], true),
                'agreeInfo' => Helper::nullableValidate($reply['agree']['agree_num'] > 0 || $reply['agree']['disagree_num'] > 0 ? $reply['agree'] : null, true),
                'signInfo' => Helper::nullableValidate($reply['signature'], true),
                'tailInfo' => Helper::nullableValidate($reply['tail_info'], true),
                'clientVersion' => $this->clientVersion,
                'created_at' => $now,
                'updated_at' => $now
            ];

            $this->profiles['parsedPostTimes']++;
            $repliesInfo[] = $currentInfo;
            if ($reply['sub_post_number'] > 0) {
                $updatedRepliesInfo[$reply['id']] = Helper::getArrayValuesByKeys($currentInfo, ['subReplyNum']);
            }
            $indexesInfo[] = array_merge(Helper::getArrayValuesByKeys($currentInfo, ['tid', 'pid', 'authorUid']), [
                'created_at' => $now,
                'updated_at' => $now,
                'postTime' => $currentInfo['postTime'],
                'type' => 'reply',
                'fid' => $this->fid
            ]);
        }
        ExceptionAdditionInfo::remove('parsingPid');

        // lazy saving to Eloquent model
        $usersInfo[$parentThreadInfo['author']['id']]['privacySettings'] = $parentThreadInfo['author']['priv_sets']; // update parent thread's author privacy settings
        $this->cacheIndexesAndUsersInfo($indexesInfo, $usersInfo);
        $this->updatedPostsInfo = array_replace($this->updatedPostsInfo, $updatedRepliesInfo); // newly added update info will override previous one by post id key
        $this->parentThreadInfo[$parentThreadInfo['id']] = [
            'tid' => $parentThreadInfo['id'],
            'antiSpamInfo' => Helper::nullableValidate($parentThreadInfo['thread_info']['antispam_info'] ?? null, true),
            'authorPhoneType' => $parentThreadInfo['thread_info']['phone_type'] ?? null,
            'updated_at' => $now
        ];
        $this->repliesInfo = array_merge($this->repliesInfo, $repliesInfo);
    }

    public function savePostsInfo(): self
    {
        $savePostsTimer = new Timer();
        if ($this->indexesInfo !== []) { // if TiebaException thrown while parsing posts, indexes list might be []
            \DB::transaction(function () {
                ExceptionAdditionInfo::set(['insertingReplies' => true]);
                $chunkInsertBufferSize = 2000;
                $replyModel = PostModelFactory::newReply($this->fid);
                foreach (static::groupNullableColumnArray($this->repliesInfo, [
                    'authorManagerType',
                    'authorExpGrade'
                ]) as $repliesInfoGroup) {
                    $replyUpdateFields = static::getUpdateFieldsWithoutExpected($repliesInfoGroup[0], $replyModel);
                    $replyModel->chunkInsertOnDuplicate($repliesInfoGroup, $replyUpdateFields, $chunkInsertBufferSize);
                }

                $threadModel = PostModelFactory::newThread($this->fid);
                foreach ($this->parentThreadInfo as $tid => $threadInfo) {
                    $threadModel->where('tid', $tid)->update($threadInfo);
                }
                ExceptionAdditionInfo::remove('insertingReplies');

                $this->saveIndexesAndUsersInfo($chunkInsertBufferSize);
            }, 5);
        }
        $savePostsTimer->stop();

        $this->profiles['savePostsTiming'] += $savePostsTimer->getTime();
        ExceptionAdditionInfo::remove('crawlingFid', 'crawlingTid');
        $this->repliesInfo = [];
        $this->indexesInfo = [];
        return $this;
    }
}
