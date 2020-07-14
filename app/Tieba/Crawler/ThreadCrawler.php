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

class ThreadCrawler extends Crawlable
{
    protected string $clientVersion = '6.0.2';

    protected int $fid;

    protected string $forumName;

    public int $startPage;

    public int $endPage;

    protected array $pagesInfo = [];

    protected array $indexesInfo = [];

    protected UsersInfoParser $usersInfo;

    protected array $threadsInfo = [];

    protected array $updatedPostsInfo = [];

    public function __construct(int $fid, string $forumName, int $startPage, ?int $endPage = null)
    {
        $this->fid = $fid;
        $this->forumName = $forumName;
        $this->startPage = $startPage;
        $defaultCrawlPageRange = 0; // by default we don't have to crawl every threads pages, only the first one
        $this->endPage = $endPage ?? $this->startPage + $defaultCrawlPageRange; // if $endPage haven't been determined, only crawl $defaultCrawlPageRange pages after $startPage
        $this->usersInfo = new UsersInfoParser();

        ExceptionAdditionInfo::set([
            'crawlingFid' => $fid,
            'crawlingForumName' => $forumName,
            'profiles' => &$this->profiles // assign by reference will sync values change with addition info
        ]);
    }

    public function getUpdatedPostsInfo(): array
    {
        return $this->updatedPostsInfo;
    }

    public function doCrawl(): self
    {
        \Log::channel('crawler-info')->info("Start to fetch threads for forum {$this->forumName}, fid {$this->fid}, page {$this->startPage}");
        ExceptionAdditionInfo::set(['parsingPage' => 1]);

        $tiebaClient = $this->getClientHelper();
        $webRequestTiming = new TimingHelper();
        $threadsInfo = json_decode($tiebaClient->post(
            'http://c.tieba.baidu.com/c/f/frs/page',
            [
                'form_params' => [
                    'kw' => $this->forumName,
                    'pn' => 1,
                    'rn' => 50
                ]
            ]
        )->getBody(), true);
        $webRequestTiming->stop();
        $this->profiles['webRequestTimes'] += 1;
        $this->profiles['webRequestTiming'] += $webRequestTiming->getTiming();

        try {
            $this->checkThenParsePostsInfo($threadsInfo);

            $webRequestTiming->start();
            // by default we don't have to crawl every sub reply pages, only first and last one
            (new GuzzleHttp\Pool(
                $tiebaClient,
                (function () use ($tiebaClient) {
                    for ($pn = $this->startPage + 1; $pn <= $this->endPage; $pn++) { // crawling page range [$startPage + 1, $endPage]
                        yield function () use ($tiebaClient, $pn) {
                            \Log::channel('crawler-info')->info("Fetch threads for forum {$this->forumName}, fid {$this->fid}, page {$pn}");
                            return $tiebaClient->postAsync(
                                'http://c.tieba.baidu.com/c/f/frs/page',
                                [
                                    'form_params' => [
                                        'kw' => $this->forumName,
                                        'pn' => $pn,
                                        'rn' => 50
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
                        $this->profiles['webRequestTimes'] += 1;
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
            default:
                throw new \RuntimeException("Error from tieba client when crawling thread, raw json: " . json_encode($responseJson));
        }

        $threadsList = $responseJson['thread_list'];
        if (count($threadsList) == 0) {
            throw new TiebaException('Forum threads list is empty, forum might doesn\'t existed');
        }

        $this->pagesInfo = $responseJson['page'];
        $totalPages = $responseJson['page']['total_page'];
        if ($this->endPage > $totalPages) { // crawl end page should be trimmed when it's larger than replies total page
            $this->endPage = $totalPages;
        }

        $this->parsePostsInfo($threadsList);
    }

    private function parsePostsInfo(array $threadsList): void
    {
        $usersInfo = [];
        $updatedThreadsInfo = [];
        $threadsInfo = [];
        $indexesInfo = [];
        $now = Carbon::now();
        foreach ($threadsList as $thread) {
            ExceptionAdditionInfo::set(['parsingTid' => $thread['tid']]);
            $usersInfo[] = $thread['author'] + ['gender' => $thread['author']['sex'] ?? null]; // sb 6.0.2
            $currentInfo = [
                'tid' => $thread['tid'],
                'firstPid' => $thread['first_post_id'],
                'threadType' => $thread['thread_types'],
                'stickyType' => $thread['is_membertop'] == 1
                    ? 'membertop'
                    : (isset($thread['is_top'])
                        ? ($thread['is_top'] == 0
                            ? null
                            : 'top')
                        : 'top'), // in 6.0.2 client version, if there's a vip sticky thread and three normal sticky threads, the fourth (oldest) thread won't have is_top field
                'isGood' => $thread['is_good'],
                'topicType' => isset($thread['is_livepost']) ? $thread['live_post_type'] : null,
                'title' => $thread['title'],
                'authorUid' => $thread['author']['id'],
                'authorManagerType' => Helper::nullableValidate($thread['author']['bawu_type'] ?? null), // topic thread won't have this
                'postTime' => isset($thread['create_time']) ? Carbon::createFromTimestamp($thread['create_time'])->toDateTimeString() : null, // topic thread won't have this
                'latestReplyTime' => Carbon::createFromTimestamp($thread['last_time_int'])->toDateTimeString(),
                'latestReplierUid' => $thread['last_replyer']['id'] ?? null, // topic thread won't have this
                'replyNum' => $thread['reply_num'],
                'viewNum' => $thread['view_num'],
                'shareNum' => $thread['share_num'] ?? null, // topic thread won't have this
                'authorPhoneType' => null, // set by ReplyCrawler parent thread info updating
                'antiSpamInfo' => null, // set by ReplyCrawler parent thread info updating
                'location' => Helper::nullableValidate($thread['location'] ?? null, true),
                'agreeInfo' => Helper::nullableValidate(isset($thread['agree']) && ($thread['agree']['agree_num'] > 0 || $thread['agree']['disagree_num'] > 0)
                    ? $thread['agree']
                    : null, true), // topic thread won't have this
                'zanInfo' => Helper::nullableValidate($thread['zan'] ?? null, true),
                'clientVersion' => $this->clientVersion,
                'created_at' => $now,
                'updated_at' => $now
            ];

            $this->profiles['parsedPostTimes'] += 1;
            $threadsInfo[] = $currentInfo;
            $updatedThreadsInfo[$thread['tid']] = Helper::getArrayValuesByKeys($currentInfo, ['latestReplyTime', 'replyNum']);
            $indexesInfo[] = [
                'created_at' => $now,
                'updated_at' => $now,
                'type' => 'thread',
                'fid' => $this->fid
            ] + Helper::getArrayValuesByKeys($currentInfo, ['tid', 'authorUid', 'postTime']);
        }
        ExceptionAdditionInfo::remove('parsingTid');

        // lazy saving to Eloquent model
        $this->profiles['parsedUserTimes'] = $this->usersInfo->parseUsersInfo(collect($usersInfo)->unique('id')->toArray());
        $this->updatedPostsInfo = $updatedThreadsInfo + $this->updatedPostsInfo; // newly added update info will override previous one by post id key
        $this->threadsInfo = array_merge($this->threadsInfo, $threadsInfo);
        $this->indexesInfo = array_merge($this->indexesInfo, $indexesInfo);
    }

    public function savePostsInfo(): self
    {
        $savePostsTiming = new TimingHelper();
        if ($this->indexesInfo != null) { // if TiebaException thrown while parsing posts, indexes list might be null
            \DB::transaction(function () {
                ExceptionAdditionInfo::set(['insertingThreads' => true]);
                $chunkInsertBufferSize = 2000;
                $threadModel = PostModelFactory::newThread($this->fid);
                foreach (static::groupNullableColumnArray($this->threadsInfo, [
                    'postTime',
                    'latestReplyTime',
                    'latestReplierUid',
                    'shareNum',
                    'agreeInfo'
                ]) as $threadsInfoGroup) {
                    $threadUpdateFields = Crawlable::getUpdateFieldsWithoutExpected($threadsInfoGroup[0], $threadModel);
                    $threadModel->chunkInsertOnDuplicate($threadsInfoGroup, $threadUpdateFields, $chunkInsertBufferSize);
                }

                $indexModel = new IndexModel();
                $indexUpdateFields = Crawlable::getUpdateFieldsWithoutExpected($this->indexesInfo[0], $indexModel);
                $indexModel->chunkInsertOnDuplicate($this->indexesInfo, $indexUpdateFields, $chunkInsertBufferSize);
                ExceptionAdditionInfo::remove('insertingThreads');

                $this->usersInfo->saveUsersInfo();
            }, 5);
        }
        $savePostsTiming->stop();

        $this->profiles['savePostsTiming'] += $savePostsTiming->getTiming();
        ExceptionAdditionInfo::remove('crawlingFid', 'crawlingForumName');
        $this->threadsInfo = [];
        $this->indexesInfo = [];
        return $this;
    }
}
