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

class SubReplyCrawler extends Crawlable
{
    protected $clientVersion = '8.8.8';

    protected $forumID;

    protected $threadID;

    protected $replyID;

    protected $usersInfo;

    protected $indexesList = [];

    protected $subRepliesList = [];

    protected $webRequestTimes = 0;

    protected $parsedPostTimes = 0;

    protected $parsedUserTimes = 0;

    protected $pagesInfo = [];

    public $startPage;

    public $endPage;

    public function doCrawl(): self
    {
        $tiebaClient = $this->getClientHelper();

        Log::info("Start to fetch sub replies for pid {$this->replyID}, tid {$this->threadID}, page {$this->startPage}");
        $startPageSubRepliesInfo = json_decode($tiebaClient->post(
            'http://c.tieba.baidu.com/c/f/pb/floor',
            [
                'form_params' => [
                    'kz' => $this->threadID,
                    'pid' => $this->replyID,
                    'pn' => $this->startPage
                ]
            ]
        )->getBody(), true);
        $this->webRequestTimes += 1;

        try {
            $this->parseSubRepliesList($startPageSubRepliesInfo);
            $totalPages = $startPageSubRepliesInfo['page']['total_page'];
            if ($this->endPage > $totalPages) { // crawl end page should be trim when it's larger than replies total page
                $this->endPage = $totalPages;
            }

            (new GuzzleHttp\Pool(
                $tiebaClient,
                (function () use ($tiebaClient) {
                    for ($pn = $this->startPage + 1; $pn <= $this->endPage; $pn++) {
                        yield function () use ($tiebaClient, $pn) {
                            Log::info("Fetch sub replies for pid {$this->replyID}, tid {$this->threadID}, page {$pn}");
                            return $tiebaClient->postAsync(
                                'http://c.tieba.baidu.com/c/f/pb/floor',
                                [
                                    'form_params' => [
                                        'kz' => $this->threadID,
                                        'pid' => $this->replyID,
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
                        $subRepliesInfo = json_decode($response->getBody(), true);
                        $this->parseSubRepliesList($subRepliesInfo);
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

    private function parseSubRepliesList(array $subRepliesJson): void
    {
        $this->pagesInfo = $subRepliesJson['page'];
        switch ($subRepliesJson['error_code']) {
            case 0:
                $subRepliesList = $subRepliesJson['subpost_list'];
                break;
            case 4: // {"error_code": "4", "error_msg": "贴子可能已被删除"}
                throw new TiebaException('Reply already deleted when crawling sub reply.');
            case 28: // {"error_code": "28", "error_msg": "您浏览的主题已不存在，去看看其他贴子吧"}
                throw new TiebaException('Thread already deleted when crawling sub reply.');
            default:
                throw new \RuntimeException('Error from tieba client when crawling sub reply, raw json: ' . json_encode($subRepliesJson));
        }
        if (count($subRepliesList) == 0) {
            throw new TiebaException('Sub reply list is empty, posts might already deleted from tieba.');
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
                'content' => Helper::nullableValidate($subReply['content'], true),
                'authorUid' => $subReply['author']['id'],
                'authorManagerType' => Helper::nullableValidate($subReply['author']['bawu_type']),
                'authorExpGrade' => $subReply['author']['level_id'],
                'postTime' => Carbon::createFromTimestamp($subReply['time'])->toDateTimeString(),
                'clientVersion' => $this->clientVersion,
                'created_at' => $now,
                'updated_at' => $now
            ];

            $this->parsedPostTimes += 1;
            $latestInfo = end($subRepliesInfo);
            $indexesInfo[] = [
                'created_at' => $now,
                'updated_at' => $now,
                'postTime' => $latestInfo['postTime'],
                'type' => 'subReply',
                'fid' => $this->forumID
            ] + Helper::getArrayValuesByKeys($latestInfo, ['tid', 'pid', 'spid', 'authorUid']);
        }
        ExceptionAdditionInfo::remove('parsingSpid');

        // lazy saving to Eloquent model
        $this->parsedUserTimes = $this->usersInfo->parseUsersList(collect($usersList)->unique('id')->toArray());
        $this->subRepliesList = array_merge($this->subRepliesList, $subRepliesInfo);
        $this->indexesList = array_merge($this->indexesList, $indexesInfo);
    }

    public function saveLists(): self
    {
        if ($this->indexesList != null) { // if TiebaException thrown while parsing posts, indexes list might be null
            \DB::transaction(function () {
                ExceptionAdditionInfo::set(['insertingSubReplies' => true]);
                $chunkInsertBufferSize = 2000;
                $subReplyModel = PostModelFactory::newSubReply($this->forumID);
                $subReplyUpdateFields = array_diff(array_keys($this->subRepliesList[0]), $subReplyModel->updateExpectFields);
                $subReplyModel->chunkInsertOnDuplicate($this->subRepliesList, $subReplyUpdateFields, $chunkInsertBufferSize);

                $indexModel = new IndexModel();
                $indexUpdateFields = array_diff(array_keys($this->indexesList[0]), $indexModel->updateExpectFields);
                $indexModel->chunkInsertOnDuplicate($this->indexesList, $indexUpdateFields, $chunkInsertBufferSize);
                ExceptionAdditionInfo::remove('insertingSubReplies');

                $this->usersInfo->saveUsersList();
            }, 5);
        }

        ExceptionAdditionInfo::remove('crawlingFid', 'crawlingTid', 'crawlingPid');
        $this->subRepliesList = [];
        $this->indexesList = [];
        return $this;
    }

    public function __construct(int $fid, int $tid, int $pid, int $startPage, int $endPage = null)
    {
        $this->forumID = $fid;
        $this->threadID = $tid;
        $this->replyID = $pid;
        $this->usersInfo = new UserInfoParser();
        $this->startPage = $startPage;
        $defaultCrawlPageRange = 100;
        $this->endPage = $endPage ?? $this->startPage + $defaultCrawlPageRange; // if $endPage haven't been determined, only crawl $defaultCrawlPageRange pages after $startPage

        ExceptionAdditionInfo::set([
            'crawlingFid' => $fid,
            'crawlingTid' => $tid,
            'crawlingPid' => $pid,
            'webRequestTimes' => &$this->webRequestTimes, // assign by reference will sync values change with addition info
            'parsedPostTimes' => &$this->parsedPostTimes,
            'parsedUserTimes' => &$this->parsedUserTimes
        ]);
    }
}
