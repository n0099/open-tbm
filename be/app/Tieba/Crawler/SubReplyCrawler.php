<?php

namespace App\Tieba\Crawler;

use App\Exceptions\ExceptionAdditionInfo;
use App\Helper;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\TiebaException;
use App\Timer;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class SubReplyCrawler extends Crawlable
{
    protected string $clientVersion = '8.8.8';

    protected int $tid;

    protected int $pid;

    protected array $subRepliesInfo = [];

    public function __construct(int $fid, int $tid, int $pid, int $startPage, ?int $endPage = null)
    {
        parent::__construct($fid, $startPage, $endPage); // by default we don't have to crawl every sub reply pages, only the first and last one
        $this->tid = $tid;
        $this->pid = $pid;

        ExceptionAdditionInfo::set([
            'crawlingTid' => $tid,
            'crawlingPid' => $pid
        ]);
    }

    public function doCrawl(): self
    {
        \Log::channel('crawler-info')->info("Start to fetch sub replies for pid {$this->pid}, tid {$this->tid}, page {$this->startPage}");
        ExceptionAdditionInfo::set(['parsingPage' => $this->startPage]);

        $tiebaClient = $this->getClientHelper();
        $webRequestTimer = new Timer();
        $startPageSubRepliesInfo = json_decode($tiebaClient->post(
            'http://c.tieba.baidu.com/c/f/pb/floor',
            [
                'form_params' => [
                    'kz' => $this->tid,
                    'pid' => $this->pid,
                    'pn' => $this->startPage
                ]
            ]
        )->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->profileWebRequestStopped($webRequestTimer);

        try {
            $this->checkThenParsePostsInfo($startPageSubRepliesInfo);

            $webRequestTimer->start();
            // by default we don't have to crawl every sub reply pages, only first and last one
            (new \GuzzleHttp\Pool(
                $tiebaClient,
                (function () use ($tiebaClient): \Generator {
                    for ($pn = $this->startPage + 1; $pn <= $this->endPage; $pn++) { // crawling page range [$startPage + 1, $endPage]
                        yield function () use ($tiebaClient, $pn): \GuzzleHttp\Promise\PromiseInterface {
                            \Log::channel('crawler-info')->info("Fetch sub replies for reply, pid {$this->pid}, tid {$this->tid}, page {$pn}");
                            return $tiebaClient->postAsync(
                                'http://c.tieba.baidu.com/c/f/pb/floor',
                                [
                                    'form_params' => [
                                        'kz' => $this->tid,
                                        'pid' => $this->pid,
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
                throw new TiebaException('Reply already deleted when crawling sub reply');
            case 28: // {"error_code": "28", "error_msg": "您浏览的主题已不存在，去看看其他贴子吧"}
                throw new TiebaException('Thread already deleted when crawling sub reply');
            default:
                throw new \RuntimeException('Error from tieba client when crawling sub reply, raw json: ' . json_encode($responseJson, JSON_THROW_ON_ERROR));
        }

        $subRepliesList = $responseJson['subpost_list'];
        if (\count($subRepliesList) === 0) {
            throw new TiebaException('Sub reply list is empty, posts might already deleted from tieba');
        }

        $this->cachePageInfoAndTrimEndPage($responseJson['page']);
        $this->parsePostsInfo($subRepliesList);
    }

    private function parsePostsInfo(array $subRepliesList): void
    {
        $usersInfo = [];
        $subRepliesInfo = [];
        $indexesInfo = [];
        $now = Carbon::now();
        foreach ($subRepliesList as $subReply) {
            ExceptionAdditionInfo::set(['parsingSpid' => $subReply['id']]);
            $usersInfo[$subReply['author']['id']] = $subReply['author'];
            $currentInfo = [
                'tid' => $this->tid,
                'pid' => $this->pid,
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

            $this->profiles['parsedPostTimes']++;
            $subRepliesInfo[] = $currentInfo;
            $indexesInfo[] = array_merge(Arr::only($currentInfo, ['tid', 'pid', 'spid', 'authorUid']), [
                'created_at' => $now,
                'updated_at' => $now,
                'postTime' => $currentInfo['postTime'],
                'type' => 'subReply',
                'fid' => $this->fid
            ]);
        }
        ExceptionAdditionInfo::remove('parsingSpid');

        // lazy saving to Eloquent model
        $this->cacheIndexesAndUsersInfo($indexesInfo, $usersInfo);
        $this->subRepliesInfo = array_merge($this->subRepliesInfo, $subRepliesInfo);
    }

    public function savePostsInfo(): self
    {
        $savePostsTimer = new Timer();
        if ($this->indexesInfo !== []) { // if TiebaException thrown while parsing posts, indexes list might be []
            \DB::transaction(function () {
                ExceptionAdditionInfo::set(['insertingSubReplies' => true]);
                $chunkInsertBufferSize = 2000;
                $subReplyModel = PostModelFactory::newSubReply($this->fid);
                $subReplyUpdateFields = static::getUpdateFieldsWithoutExpected($this->subRepliesInfo[0], $subReplyModel);
                $subReplyModel->chunkInsertOnDuplicate($this->subRepliesInfo, $subReplyUpdateFields, $chunkInsertBufferSize);
                ExceptionAdditionInfo::remove('insertingSubReplies');

                $this->saveIndexesAndUsersInfo($chunkInsertBufferSize);
            }, 5);
        }
        $savePostsTimer->stop();

        $this->profiles['savePostsTiming'] += $savePostsTimer->getTime();
        ExceptionAdditionInfo::remove('crawlingFid', 'crawlingTid', 'crawlingPid');
        $this->subRepliesInfo = [];
        $this->indexesInfo = [];
        return $this;
    }
}
