<?php

namespace App\Tieba\Crawler;

use App\Tieba\Eloquent\IndexModel;
use App\Exceptions\ExceptionAdditionInfo;
use App\Helper;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\TiebaException;
use App\TimingHelper;
use Carbon\Carbon;
use GuzzleHttp;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;

class SubReplyCrawler extends Crawlable
{
    protected string $clientVersion = '8.8.8';

    protected int $fid;

    protected int $tid;

    protected int $pid;

    public int $startPage;

    public int $endPage;

    protected array $pagesInfo = [];

    protected array $indexesInfo = [];

    protected UsersInfoParser $usersInfo;

    protected array $subRepliesInfo = [];

    public function __construct(int $fid, int $tid, int $pid, int $startPage, ?int $endPage = null)
    {
        $this->fid = $fid;
        $this->tid = $tid;
        $this->pid = $pid;
        $this->startPage = $startPage;
        $defaultCrawlPageRange = 0; // by default we don't have to crawl every sub reply pages, only the first and last one
        $this->endPage = $endPage ?? $this->startPage + $defaultCrawlPageRange; // if $endPage haven't been determined, only crawl $defaultCrawlPageRange pages after $startPage
        $this->usersInfo = new UsersInfoParser();

        ExceptionAdditionInfo::set([
            'crawlingFid' => $fid,
            'crawlingTid' => $tid,
            'crawlingPid' => $pid,
            'profiles' => &$this->profiles // assign by reference will sync values change with addition info
        ]);
    }

    public function doCrawl(): self
    {
        \Log::channel('crawler-info')->info("Start to fetch sub replies for pid {$this->pid}, tid {$this->tid}, page {$this->startPage}");
        ExceptionAdditionInfo::set(['parsingPage' => $this->startPage]);

        $tiebaClient = $this->getClientHelper();
        $webRequestTiming = new TimingHelper();
        $startPageSubRepliesInfo = json_decode($tiebaClient->post(
            'http://c.tieba.baidu.com/c/f/pb/floor',
            [
                'form_params' => [
                    'kz' => $this->tid,
                    'pid' => $this->pid,
                    'pn' => $this->startPage
                ]
            ]
        )->getBody(), true);
        $webRequestTiming->stop();
        $this->profiles['webRequestTimes']++;
        $this->profiles['webRequestTiming'] += $webRequestTiming->getTiming();

        try {
            $this->checkThenParsePostsInfo($startPageSubRepliesInfo);

            $webRequestTiming->start();
            // by default we don't have to crawl every sub reply pages, only first and last one
            (new GuzzleHttp\Pool(
                $tiebaClient,
                (function () use ($tiebaClient) {
                    for ($pn = $this->startPage + 1; $pn <= $this->endPage; $pn++) { // crawling page range [$startPage + 1, $endPage]
                        yield function () use ($tiebaClient, $pn) {
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
                [
                    'concurrency' => 10,
                    'fulfilled' => function (\Psr\Http\Message\ResponseInterface $response, int $index) use ($webRequestTiming) {
                        $webRequestTiming->stop();
                        $this->profiles['webRequestTimes']++;
                        $this->profiles['webRequestTiming'] += $webRequestTiming->getTiming();
                        ExceptionAdditionInfo::set(['parsingPage' => $index]);
                        $this->checkThenParsePostsInfo(json_decode($response->getBody(), true));
                        $webRequestTiming->start(); // resume timing for possible succeed web request
                    },
                    'rejected' => function (GuzzleHttp\Exception\RequestException $e, int $index) {
                        ExceptionAdditionInfo::set(['parsingPage' => $index]);
                        report($e);
                    }
                ]
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
                throw new \RuntimeException('Error from tieba client when crawling sub reply, raw json: ' . json_encode($responseJson));
        }

        $subRepliesList = $responseJson['subpost_list'];
        if (\count($subRepliesList) == 0) {
            throw new TiebaException('Sub reply list is empty, posts might already deleted from tieba');
        }

        $this->pagesInfo = $responseJson['page'];
        $totalPages = $responseJson['page']['total_page'];
        if ($this->endPage > $totalPages) { // crawl end page should be trimmed when it's larger than replies total page
            $this->endPage = $totalPages;
        }

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
            $usersInfo[] = $subReply['author'];
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
            $indexesInfo[] = [
                'created_at' => $now,
                'updated_at' => $now,
                'postTime' => $currentInfo['postTime'],
                'type' => 'subReply',
                'fid' => $this->fid
            ] + Helper::getArrayValuesByKeys($currentInfo, ['tid', 'pid', 'spid', 'authorUid']);
        }
        ExceptionAdditionInfo::remove('parsingSpid');

        // lazy saving to Eloquent model
        $this->profiles['parsedUserTimes'] = $this->usersInfo->parseUsersInfo(collect($usersInfo)->unique('id')->toArray());
        $this->subRepliesInfo = array_merge($this->subRepliesInfo, $subRepliesInfo);
        $this->indexesInfo = array_merge($this->indexesInfo, $indexesInfo);
    }

    public function savePostsInfo(): self
    {
        $savePostsTiming = new TimingHelper();
        if ($this->indexesInfo != null) { // if TiebaException thrown while parsing posts, indexes list might be null
            \DB::transaction(function () {
                ExceptionAdditionInfo::set(['insertingSubReplies' => true]);
                $chunkInsertBufferSize = 2000;
                $subReplyModel = PostModelFactory::newSubReply($this->fid);
                $subReplyUpdateFields = Crawlable::getUpdateFieldsWithoutExpected($this->subRepliesInfo[0], $subReplyModel);
                $subReplyModel->chunkInsertOnDuplicate($this->subRepliesInfo, $subReplyUpdateFields, $chunkInsertBufferSize);

                $indexModel = new IndexModel();
                $indexUpdateFields = Crawlable::getUpdateFieldsWithoutExpected($this->indexesInfo[0], $indexModel);
                $indexModel->chunkInsertOnDuplicate($this->indexesInfo, $indexUpdateFields, $chunkInsertBufferSize);
                ExceptionAdditionInfo::remove('insertingSubReplies');

                $this->usersInfo->saveUsersInfo();
            }, 5);
        }
        $savePostsTiming->stop();

        $this->profiles['savePostsTiming'] += $savePostsTiming->getTiming();
        ExceptionAdditionInfo::remove('crawlingFid', 'crawlingTid', 'crawlingPid');
        $this->subRepliesInfo = [];
        $this->indexesInfo = [];
        return $this;
    }
}
