<?php

namespace App\Tieba\Crawler;

use App\Tieba\Eloquent;
use Carbon\Carbon;
use GuzzleHttp;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;

class ThreadCrawler extends Crawlable
{
    protected $forumName;

    protected $clientVersion = '6.0.2';

    protected $threadIDList = [];

    public function doCrawl() : self
    {
        $client = $this->getClientHelper();

        $threadsList = json_decode($client->post(
            'http://c.tieba.baidu.com/c/f/frs/page',
            ['form_params' => ['kw' => $this->forumName, 'pn' => 1, 'rn' => 50]]
        )->getBody(), true);

        $this->parseThreadsList($threadsList);

        return $this;
    }

    private function parseThreadsList(array $threadsJson)
    {
        if ($threadsJson['error_code'] == 0) {
            $threadsList = $threadsJson['thread_list'];
        } else {
            throw new \HttpResponseException("Error from tieba client, raw json: " . json_encode($threadsJson));
        }
        if (count($threadsList) == 0) {
            throw new \LengthException('Forum posts list is empty');
        }

        $usersList = [];
        $threadIDList = [];
        $threadsInfo = [];
        $indexesInfo = [];
        foreach ($threadsList as $thread) {
            $usersList[] = $thread['author'] + ['gender' => $thread['author']['sex']]; // sb 6.0.2
            $threadIDList[] = $thread['tid'];
            $threadsInfo[] = [
                'tid' => $thread['tid'],
                'firstPid' => $thread['first_post_id'],
                'isSticky' => $thread['is_top'],
                'isGood' => $thread['is_good'],
                'title' => $thread['title'],
                'authorUid' => $thread['author']['id'],
                'authorManagerType' => self::valueValidate($thread['author']['bawu_type']),
                'postTime' => Carbon::createFromTimestamp($thread['create_time'])->toDateTimeString(),
                'latestReplyTime' => Carbon::createFromTimestamp($thread['last_time_int'])->toDateTimeString(),
                'latestReplierUid' => $thread['last_replyer']['id'],
                'replyNum' => $thread['reply_num'],
                'viewNum' => $thread['view_num'],
                'shareNum' => $thread['share_num'],
                'agreeInfo' => self::valueValidate(($thread['agree']['has_agree'] > 0 ? $thread['agree'] : null), true),
                'zanInfo' => self::valueValidate($thread['zan'], true),
                'locationInfo' => self::valueValidate($thread['location'], true),
                'clientVersion' => $this->clientVersion,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
            $indexesInfo[] = [
                'fid' => $this->forumId,
                'type' => 'thread',
                'tid' => end($threadsInfo)['tid'],
                'authorUid' => end($threadsInfo)['authorUid'],
                'postTime' => end($threadsInfo)['postTime']
            ];
        }
        $threadUpdateExceptFields = array_diff(array_keys($threadsInfo[0]), [
            'tid',
            'title',
            'postTime',
            'authorUid',
            'created_at'
        ]);

        $this->parseUsersList(collect($usersList)->unique('id')->toArray());
        $this->threadIDList = $threadIDList;
        Eloquent\ModelFactory::newThread($this->forumId)->insertOnDuplicateKey($threadsInfo, $threadUpdateExceptFields);
        (new Eloquent\IndexModel())->insertOnDuplicateKey($indexesInfo);
    }

    public function getTid() : array
    {
        return $this->threadIDList;
    }

    public function __construct(int $forumId, string $forumName)
    {
        $this->forumId = $forumId;
        $this->forumName = $forumName;
    }
}
