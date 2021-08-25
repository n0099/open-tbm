<?php

namespace App\Tieba\Crawler;

use App\Exceptions\ExceptionAdditionInfo;
use App\Helper;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\TiebaException;
use App\Timer;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class ThreadCrawler extends Crawlable
{
    protected string $clientVersion = '6.0.2';

    protected string $forumName;

    protected array $threadsInfo = [];

    protected array $updatedPostsInfo = [];

    public function __construct(int $fid, string $forumName, int $startPage, ?int $endPage = null)
    {
        parent::__construct($fid, $startPage, $endPage); // by default we don't have to crawl every threads pages, only the first one
        $this->forumName = $forumName;
        ExceptionAdditionInfo::set(['crawlingForumName' => $forumName]);
    }

    public function getUpdatedPostsInfo(): array
    {
        return $this->updatedPostsInfo;
    }

    public function doCrawl(): self
    {
        \Log::channel('crawler-info')->info("Start to fetch threads for forum {$this->forumName}, fid {$this->fid}, page {$this->startPage}");
        ExceptionAdditionInfo::set(['parsingPage' => $this->startPage]);

        $tiebaClient = $this->getClientHelper();
        $webRequestTimer = new Timer();
        $threadsInfo = json_decode($tiebaClient->post(
            'http://c.tieba.baidu.com/c/f/frs/page',
            [
                'form_params' => [
                    'kw' => $this->forumName,
                    'pn' => 1,
                    'rn' => 50
                ]
            ]
        )->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        $this->profileWebRequestStopped($webRequestTimer);

        try {
            $this->checkThenParsePostsInfo($threadsInfo);

            $webRequestTimer->start();
            (new \GuzzleHttp\Pool(
                $tiebaClient,
                (function () use ($tiebaClient): \Generator {
                    for ($pn = $this->startPage + 1; $pn <= $this->endPage; $pn++) { // crawling page range [$startPage + 1, $endPage]
                        yield function () use ($tiebaClient, $pn): \GuzzleHttp\Promise\PromiseInterface {
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
            default:
                throw new \RuntimeException('Error from tieba client when crawling thread, raw json: ' . json_encode($responseJson, JSON_THROW_ON_ERROR));
        }

        $threadsList = $responseJson['thread_list'];
        if (\count($threadsList) === 0) {
            throw new TiebaException('Forum threads list is empty, forum might doesn\'t existed');
        }

        $this->cachePageInfoAndTrimEndPage($responseJson['page']);
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
            $usersInfo[$thread['author']['id']] = array_merge($thread['author'], ['gender' => $thread['author']['sex'] ?? null]); // gender is named as sex in tieba client 6.0.2
            $currentInfo = [
                'tid' => $thread['tid'],
                'firstPid' => $thread['first_post_id'],
                'threadType' => $thread['thread_types'],
                'stickyType' => (int)$thread['is_membertop'] === 1
                    ? 'membertop'
                    : (isset($thread['is_top'])
                        ? ((int)$thread['is_top'] === 0
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

            $this->profiles['parsedPostTimes']++;
            $threadsInfo[] = $currentInfo;
            $updatedThreadsInfo[$thread['tid']] = Arr::only($currentInfo, ['latestReplyTime', 'replyNum']);
            $indexesInfo[] = array_merge(Arr::only($currentInfo, ['tid', 'authorUid', 'postTime']), [
                'created_at' => $now,
                'updated_at' => $now,
                'type' => 'thread',
                'fid' => $this->fid
            ]);
        }
        ExceptionAdditionInfo::remove('parsingTid');

        // lazy saving to Eloquent model
        $this->cacheIndexesAndUsersInfo($indexesInfo, $usersInfo);
        $this->updatedPostsInfo = array_replace($this->updatedPostsInfo, $updatedThreadsInfo); // newly added update info will override previous one by post id key
        $this->threadsInfo = array_merge($this->threadsInfo, $threadsInfo);
    }

    public function savePostsInfo(): self
    {
        $savePostsTimer = new Timer();
        if ($this->indexesInfo !== []) { // if TiebaException have thrown while parsing posts, indexes list might be []
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
                    $threadUpdateFields = static::getUpdateFieldsWithoutExpected($threadsInfoGroup[0], $threadModel);
                    $threadModel->chunkInsertOnDuplicate($threadsInfoGroup, $threadUpdateFields, $chunkInsertBufferSize);
                }
                ExceptionAdditionInfo::remove('insertingThreads');

                $this->saveIndexesAndUsersInfo($chunkInsertBufferSize);
            }, 5);
        }
        $savePostsTimer->stop();

        $this->profiles['savePostsTiming'] += $savePostsTimer->getTime();
        ExceptionAdditionInfo::remove('crawlingFid', 'crawlingForumName');
        $this->threadsInfo = [];
        $this->indexesInfo = [];
        return $this;
    }
}
