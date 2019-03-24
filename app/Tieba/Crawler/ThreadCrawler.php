<?php

namespace App\Tieba\Crawler;

use App\Tieba\Eloquent\IndexModel;
use App\Exceptions\ExceptionAdditionInfo;
use App\Helper;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\TiebaException;
use Carbon\Carbon;
use GuzzleHttp;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;

class ThreadCrawler extends Crawlable
{
    protected $clientVersion = '6.0.2';

    protected $forumID;

    protected $forumName;

    protected $usersInfo;

    protected $threadsList = [];

    protected $indexesList = [];

    protected $threadsUpdateInfo = [];

    protected $webRequestTimes = 0;

    protected $parsedPostTimes = 0;

    protected $parsedUserTimes = 0;

    protected $pagesInfo = [];

    public $startPage;

    public $endPage;

    public function doCrawl(): self
    {
        \Log::channel('crawler-info')->info("Start to fetch threads for forum {$this->forumName}, fid {$this->forumID}, page {$this->startPage}");
        ExceptionAdditionInfo::set(['parsingPage' => 1]);

        $tiebaClient = $this->getClientHelper();
        $threadsList = json_decode($tiebaClient->post(
            'http://c.tieba.baidu.com/c/f/frs/page',
            [
                'form_params' => [
                    'kw' => $this->forumName,
                    'pn' => 1,
                    'rn' => 50
                ]
            ]
        )->getBody(), true);
        $this->webRequestTimes += 1;

        try {
            $this->checkThenParsePostsList($threadsList);

            // by default we doesn't have to crawl every sub reply pages, only first and last one
            (new GuzzleHttp\Pool(
                $tiebaClient,
                (function () use ($tiebaClient) {
                    for ($pn = $this->startPage + 1; $pn <= $this->endPage; $pn++) { // crawling page range [$startPage + 1, $endPage]
                        yield function () use ($tiebaClient, $pn) {
                            \Log::channel('crawler-info')->info("Fetch threads for forum {$this->forumName}, fid {$this->forumID}, page {$pn}");
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
                    'fulfilled' => function (\Psr\Http\Message\ResponseInterface $response, int $index) {
                        $this->webRequestTimes += 1;
                        ExceptionAdditionInfo::set(['parsingPage' => $index]);
                        $this->checkThenParsePostsList(json_decode($response->getBody(), true));
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

    protected function checkThenParsePostsList(array $responseJson): void
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
        $this->parseThreadsList($threadsList);
    }

    private function parseThreadsList(array $threadsList): void
    {
        $usersList = [];
        $threadsUpdateInfo = [];
        $threadsInfo = [];
        $indexesInfo = [];
        $now = Carbon::now();
        foreach ($threadsList as $thread) {
            ExceptionAdditionInfo::set(['parsingTid' => $thread['tid']]);
            $usersList[] = $thread['author'] + ['gender' => $thread['author']['sex'] ?? null]; // sb 6.0.2
            $threadsInfo[] = [
                'tid' => $thread['tid'],
                'firstPid' => $thread['first_post_id'],
                'stickyType' => $thread['is_membertop'] == 1
                    ? 'membertop'
                    : isset($thread['is_top']) // in 6.0.2 client version, if there's a vip sticky thread and three normal sticky threads, the first(oldest) thread won't have is_top field
                        ? $thread['is_top'] == 0
                            ? null
                            : 'top'
                        : null,
                'isGood' => $thread['is_good'],
                "topicType" => isset($thread['is_livepost']) ? $thread['live_post_type'] : null,
                'title' => $thread['title'],
                'authorUid' => $thread['author']['id'],
                'authorManagerType' => Helper::nullableValidate($thread['author']['bawu_type'] ?? null), // topic thread won't have this
                'postTime' => isset($thread['create_time']) ? Carbon::createFromTimestamp($thread['create_time'])->toDateTimeString() : null, // topic thread won't have this
                'latestReplyTime' => Carbon::createFromTimestamp($thread['last_time_int'])->toDateTimeString(),
                'latestReplierUid' => $thread['last_replyer']['id'] ?? null, // topic thread won't have this
                'replyNum' => $thread['reply_num'],
                'viewNum' => $thread['view_num'],
                'shareNum' => $thread['share_num'] ?? null, // topic thread won't have this
                'agreeInfo' => Helper::nullableValidate(isset($thread['agree']) && ($thread['agree']['agree_num'] > 0 || $thread['agree']['disagree_num'] > 0)
                    ? $thread['agree']
                    : null, true), // topic thread won't have this
                'zanInfo' => Helper::nullableValidate($thread['zan'] ?? null, true),
                'locationInfo' => Helper::nullableValidate($thread['location'] ?? null, true),
                'clientVersion' => $this->clientVersion,
                'created_at' => $now,
                'updated_at' => $now
            ];

            $this->parsedPostTimes += 1;
            $latestInfo = end($threadsInfo);
            $threadsUpdateInfo[$thread['tid']] = Helper::getArrayValuesByKeys($latestInfo, ['latestReplyTime', 'replyNum']);
            $indexesInfo[] = [
                'created_at' => $now,
                'updated_at' => $now,
                'type' => 'thread',
                'fid' => $this->forumID
            ] + Helper::getArrayValuesByKeys($latestInfo, ['tid', 'authorUid', 'postTime']);
        }
        ExceptionAdditionInfo::remove('parsingTid');

        // lazy saving to Eloquent model
        $this->parsedUserTimes = $this->usersInfo->parseUsersList(collect($usersList)->unique('id')->toArray());
        $this->threadsUpdateInfo = $threadsUpdateInfo;
        $this->threadsList = $threadsInfo;
        $this->indexesList = $indexesInfo;
    }

    public function saveLists(): self
    {
        if ($this->indexesList != null) { // if TiebaException thrown while parsing posts, indexes list might be null
            \DB::transaction(function () {
                ExceptionAdditionInfo::set(['insertingThreads' => true]);
                $chunkInsertBufferSize = 2000;
                $threadModel = PostModelFactory::newThread($this->forumID);
                foreach (static::groupNullableColumnArray($this->threadsList, [
                    'postTime',
                    'latestReplyTime',
                    'latestReplierUid',
                    'shareNum',
                    'agreeInfo'
                ]) as $threadsListGroup) {
                    $threadUpdateFields = array_diff(array_keys($threadsListGroup[0]), $threadModel->updateExpectFields);
                    $threadModel->chunkInsertOnDuplicate($threadsListGroup, $threadUpdateFields, $chunkInsertBufferSize);
                }

                $indexModel = new IndexModel();
                $indexUpdateFields = array_diff(array_keys($this->indexesList[0]), $indexModel->updateExpectFields);
                $indexModel->chunkInsertOnDuplicate($this->indexesList, $indexUpdateFields, $chunkInsertBufferSize);
                ExceptionAdditionInfo::remove('insertingThreads');

                $this->usersInfo->saveUsersList();
            }, 5);
        }

        ExceptionAdditionInfo::remove('crawlingFid', 'crawlingForumName');
        $this->threadsList = [];
        $this->indexesList = [];
        return $this;
    }

    public function getPostsIsUpdateInfo(): array
    {
        return $this->threadsUpdateInfo;
    }

    public function __construct(string $forumName, int $forumID, int $startPage, int $endPage = null)
    {
        $this->forumID = $forumID;
        $this->forumName = $forumName;
        $this->usersInfo = new UserInfoParser();
        $this->startPage = $startPage;
        $defaultCrawlPageRange = 0; // by default we doesn't have to crawl every threads pages, only first one
        $this->endPage = $endPage ?? $this->startPage + $defaultCrawlPageRange; // if $endPage haven't been determined, only crawl $defaultCrawlPageRange pages after $startPage

        ExceptionAdditionInfo::set([
            'crawlingFid' => $forumID,
            'crawlingForumName' => $forumName,
            'webRequestTimes' => &$this->webRequestTimes, // assign by reference will sync values change with addition info
            'parsedPostTimes' => &$this->parsedPostTimes,
            'parsedUserTimes' => &$this->parsedUserTimes
        ]);
    }
}
