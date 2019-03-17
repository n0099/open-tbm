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

    protected $pagesInfo = [];

    public $startPage;

    public $endPage;

    public function doCrawl(): self
    {
        Log::info("Start to fetch replies for thread, tid {$this->threadID}, page {$this->startPage}");
        ExceptionAdditionInfo::set(['parsingPage' => $this->startPage]);

        $tiebaClient = $this->getClientHelper();
        $startPageRepliesInfo = json_decode($tiebaClient->post(
            'http://c.tieba.baidu.com/c/f/pb/page',
            [
                'form_params' => [ // reverse order will be ['last' => 1, 'r' => 1]
                    'kz' => $this->threadID,
                    'pn' => $this->startPage
                ]
            ]
        )->getBody(), true);
        $this->webRequestTimes += 1;

        try {
            $this->checkThenParsePostsList($startPageRepliesInfo);
            $totalPages = $startPageRepliesInfo['page']['total_page'];
            if ($this->endPage > $totalPages) { // crawl end page should be trim when it's larger than replies total page
                $this->endPage = $totalPages;
            }

            (new GuzzleHttp\Pool(
                $tiebaClient,
                (function () use ($tiebaClient) {
                    for ($pn = $this->startPage + 1; $pn < $this->endPage; $pn++) { // crawling page range [$startPage + 1, $endPage)
                        yield function () use ($tiebaClient, $pn) {
                            Log::info("Fetching replies for thread, tid {$this->threadID}, page {$pn}");
                            return $tiebaClient->postAsync(
                                'http://c.tieba.baidu.com/c/f/pb/page',
                                [
                                    'form_params' => [
                                        'kz' => $this->threadID,
                                        'pn' => $pn
                                    ]
                                ]
                            );
                        };
                    }
                })(),
                [
                    'concurrency' => 10,
                    'fulfilled' => function (\Psr\Http\Message\ResponseInterface $response, int $index) {
                        $this->webRequestTimes += 1;
                        ExceptionAdditionInfo::set(['parsingPage' => $index]);
                        $repliesInfo = json_decode($response->getBody(), true);
                        $this->checkThenParsePostsList($repliesInfo);
                    },
                    'rejected' => function (GuzzleHttp\Exception\RequestException $e, int $index) {
                        ExceptionAdditionInfo::set(['parsingPage' => $index]);
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

    protected function checkThenParsePostsList(array $responseJson): void
    {
        switch ($responseJson['error_code']) {
            case 0: // no error
                break;
            case 4: // {"error_code": "4", "error_msg": "贴子可能已被删除"}
                throw new TiebaException('Thread already deleted when crawling reply');
            default:
                throw new \RuntimeException('Error from tieba client when crawling reply, raw json: ' . json_encode($responseJson));
        }

        $repliesList = $responseJson['post_list'];
        $repliesUserList = Helper::convertIDListKey($responseJson['user_list'], 'id');
        if (count($repliesList) == 0) {
            throw new TiebaException('Reply list is empty, posts might already deleted from tieba');
        }
        $this->pagesInfo = $responseJson['page'];
        $this->parseRepliesList($repliesList, $repliesUserList);
    }

    private function parseRepliesList(array $repliesList, $usersList): void
    {
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
                'content' => Helper::nullableValidate($reply['content'], true),
                'authorUid' => $reply['author_id'],
                'authorManagerType' => Helper::nullableValidate($usersList[$reply['author_id']]['bawu_type'] ?? null), // might be null for unknown reason
                'authorExpGrade' => Helper::nullableValidate($usersList[$reply['author_id']]['level_id'] ?? null), // might be null for unknown reason
                'subReplyNum' => $reply['sub_post_number'],
                'postTime' => Carbon::createFromTimestamp($reply['time'])->toDateTimeString(),
                'isFold' => $reply['is_fold'],
                'agreeInfo' => Helper::nullableValidate(($reply['agree']['has_agree'] > 0 ? $reply['agree'] : null), true),
                'signInfo' => Helper::nullableValidate($reply['signature'], true),
                'tailInfo' => Helper::nullableValidate($reply['tail_info'], true),
                'clientVersion' => $this->clientVersion,
                'created_at' => $now,
                'updated_at' => $now
            ];

            $this->parsedPostTimes += 1;
            $latestInfo = end($repliesInfo);
            if ($reply['sub_post_number'] > 0) {
                $repliesUpdateInfo[$reply['id']] = Helper::getArrayValuesByKeys($latestInfo, ['subReplyNum']);
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
            }, 5);
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

    public function __construct(int $fid, int $tid, int $startPage, int $endPage = null)
    {
        $this->forumID = $fid;
        $this->threadID = $tid;
        $this->usersInfo = new UserInfoParser();
        $this->startPage = $startPage;
        $defaultCrawlPageRange = 100;
        $this->endPage = $endPage ?? $this->startPage + $defaultCrawlPageRange; // if $endPage haven't been determined, only crawl $defaultCrawlPageRange pages after $startPage

        ExceptionAdditionInfo::set([
            'crawlingFid' => $fid,
            'crawlingTid' => $tid,
            'webRequestTimes' => &$this->webRequestTimes, // assign by reference will sync values change with addition info
            'parsedPostTimes' => &$this->parsedPostTimes,
            'parsedUserTimes' => &$this->parsedUserTimes
        ]);
    }
}
