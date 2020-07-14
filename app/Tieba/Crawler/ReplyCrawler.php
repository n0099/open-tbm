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

class ReplyCrawler extends Crawlable
{
    protected string $clientVersion = '8.8.8';

    protected int $fid;

    protected int $tid;

    protected UsersInfoParser $usersInfo;

    protected array $parentThreadInfo = [];

    protected array $repliesInfo = [];

    protected array $indexesInfo = [];

    protected array $updatedPostsInfo = [];

    protected array $pagesInfo = [];

    public int $startPage;

    public int $endPage;

    public function __construct(int $fid, int $tid, int $startPage, ?int $endPage = null)
    {
        $this->fid = $fid;
        $this->tid = $tid;
        $this->usersInfo = new UsersInfoParser();
        $this->startPage = $startPage;
        $defaultCrawlPageRange = 100;
        $this->endPage = $endPage ?? $this->startPage + $defaultCrawlPageRange; // if $endPage haven't been determined, only crawl $defaultCrawlPageRange pages after $startPage

        ExceptionAdditionInfo::set([
            'crawlingFid' => $fid,
            'crawlingTid' => $tid,
            'profiles' => &$this->profiles // assign by reference will sync values change with addition info
        ]);
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
        $webRequestTiming = new TimingHelper();
        $startPageRepliesInfo = json_decode($tiebaClient->post(
            'http://c.tieba.baidu.com/c/f/pb/page',
            [
                'form_params' => [ // reverse order will be ['last' => 1, 'r' => 1]
                    'kz' => $this->tid,
                    'pn' => $this->startPage
                ]
            ]
        )->getBody(), true);
        $webRequestTiming->stop();
        $this->profiles['webRequestTimes'] += 1;
        $this->profiles['webRequestTiming'] += $webRequestTiming->getTiming();

        try {
            $this->checkThenParsePostsInfo($startPageRepliesInfo);

            $webRequestTiming->start();
            (new GuzzleHttp\Pool(
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
            case 4: // {"error_code": "4", "error_msg": "贴子可能已被删除"}
                throw new TiebaException('Thread already deleted when crawling reply');
            default:
                throw new \RuntimeException('Error from tieba client when crawling reply, raw json: ' . json_encode($responseJson));
        }

        $parentThreadInfo = $responseJson['thread'];
        $repliesList = $responseJson['post_list'];
        $replyUsersList = Helper::setKeyWithItemsValue($responseJson['user_list'], 'id');
        if (count($repliesList) == 0) {
            throw new TiebaException('Reply list is empty, posts might already deleted from tieba');
        }

        $this->pagesInfo = $responseJson['page'];
        $totalPages = $responseJson['page']['total_page'];
        if ($this->endPage > $totalPages) { // crawl end page should be trimmed when it's larger than replies total page
            $this->endPage = $totalPages;
        }

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

            $this->profiles['parsedPostTimes'] += 1;
            $repliesInfo[] = $currentInfo;
            if ($reply['sub_post_number'] > 0) {
                $updatedRepliesInfo[$reply['id']] = Helper::getArrayValuesByKeys($currentInfo, ['subReplyNum']);
            }
            $indexesInfo[] = [
                'created_at' => $now,
                'updated_at' => $now,
                'postTime' => $currentInfo['postTime'],
                'type' => 'reply',
                'fid' => $this->fid
            ] + Helper::getArrayValuesByKeys($currentInfo, ['tid', 'pid', 'authorUid']);
        }
        ExceptionAdditionInfo::remove('parsingPid');

        // lazy saving to Eloquent model
        $usersInfo[$parentThreadInfo['author']['id']]['privacySettings'] = $parentThreadInfo['author']['priv_sets']; // parent thread author privacy settings
        $this->profiles['parsedUserTimes'] = $this->usersInfo->parseUsersInfo($usersInfo);
        $this->updatedPostsInfo = $updatedRepliesInfo + $this->updatedPostsInfo; // newly added update info will override previous one by post id key
        $this->parentThreadInfo[$parentThreadInfo['id']] = [
            'tid' => $parentThreadInfo['id'],
            'antiSpamInfo' => Helper::nullableValidate($parentThreadInfo['thread_info']['antispam_info'] ?? null, true),
            'authorPhoneType' => $parentThreadInfo['thread_info']['phone_type'] ?? null,
            'updated_at' => $now
        ];
        $this->repliesInfo = array_merge($this->repliesInfo, $repliesInfo);
        $this->indexesInfo = array_merge($this->indexesInfo, $indexesInfo);
    }

    public function savePostsInfo(): self
    {
        $savePostsTiming = new TimingHelper();
        if ($this->indexesInfo != null) { // if TiebaException thrown while parsing posts, indexes list might be null
            \DB::transaction(function () {
                ExceptionAdditionInfo::set(['insertingReplies' => true]);
                $chunkInsertBufferSize = 2000;
                $replyModel = PostModelFactory::newReply($this->fid);
                foreach (static::groupNullableColumnArray($this->repliesInfo, [
                    'authorManagerType',
                    'authorExpGrade'
                ]) as $repliesInfoGroup) {
                    $replyUpdateFields = Crawlable::getUpdateFieldsWithoutExpected($repliesInfoGroup[0], $replyModel);
                    $replyModel->chunkInsertOnDuplicate($repliesInfoGroup, $replyUpdateFields, $chunkInsertBufferSize);
                }

                $threadModel = PostModelFactory::newThread($this->fid);
                foreach ($this->parentThreadInfo as $tid => $threadInfo) {
                    $threadModel->where('tid', $tid)->update($threadInfo);
                }

                $indexModel = new IndexModel();
                $indexUpdateFields = Crawlable::getUpdateFieldsWithoutExpected($this->indexesInfo[0], $indexModel);
                $indexModel->chunkInsertOnDuplicate($this->indexesInfo, $indexUpdateFields, $chunkInsertBufferSize);
                ExceptionAdditionInfo::remove('insertingReplies');

                $this->usersInfo->saveUsersInfo();
            }, 5);
        }
        $savePostsTiming->stop();

        $this->profiles['savePostsTiming'] += $savePostsTiming->getTiming();
        ExceptionAdditionInfo::remove('crawlingFid', 'crawlingTid');
        $this->repliesInfo = [];
        $this->indexesInfo = [];
        return $this;
    }
}
