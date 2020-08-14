<?php

namespace App\Tieba\Crawler;

use App\Exceptions\ExceptionAdditionInfo;
use App\Tieba\ClientRequester;
use App\Tieba\Eloquent\IndexModel;

abstract class Crawlable
{
    protected string $clientVersion;

    protected int $fid;

    public int $startPage;

    public int $endPage;

    protected array $pageInfo = [];

    protected array $indexesInfo = [];

    protected UsersInfoParser $usersInfo;

    protected array $profiles = [
        'webRequestTiming' => 0,
        'savePostsTiming' => 0,
        'webRequestTimes' => 0,
        'parsedPostTimes' => 0,
        'parsedUserTimes' => 0,
    ];

    abstract public function doCrawl(): self;

    abstract protected function checkThenParsePostsInfo(array $responseJson): void;

    abstract public function savePostsInfo(): self;

    protected function __construct(int $fid, int $startPage, ?int $endPage = null, int $crawlPageRange = 0)
    {
        $this->fid = $fid;
        $this->startPage = $startPage;
        $this->endPage = $endPage ?? $this->startPage + $crawlPageRange; // if $endPage haven't been determined, only crawl $crawlPageRange pages after $startPage
        $this->usersInfo = new UsersInfoParser();
        ExceptionAdditionInfo::set([
            'crawlingFid' => $fid,
            'profiles' => &$this->profiles // assign by reference will sync values change with addition info
        ]);
    }

    public function getPages() : array
    {
        return $this->pageInfo;
    }

    public function getProfiles(): array
    {
        return $this->profiles;
    }

    protected function getClientHelper(): ClientRequester
    {
        // enable guzzle integrate with laravel debugbar
        /*
        $debugBar = resolve('debugbar');

        $timeline = $debugBar->getCollector('time');
        $profiler = new GuzzleHttp\Profiling\Debugbar\Profiler($timeline);

        $stack = GuzzleHttp\HandlerStack::create();
        $stack->unshift(new GuzzleHttp\Profiling\Middleware($profiler));

        $logger = $debugBar->getCollector('messages');
        $stack->push(GuzzleHttp\Middleware::log($logger, new GuzzleHttp\MessageFormatter()));
        $stack->push(GuzzleHttp\Middleware::log(\Log::getLogger(), new GuzzleHttp\MessageFormatter('{code} {host}{target} {error}')));
        */

        return new ClientRequester([
            // 'handler' => $stack,
            'client_version' => $this->clientVersion
        ]);
    }

    /**
     * Group INSERT sql statement to prevent updating cover old data with null values
     *
     * @param array $arrayToGroup
     * @param array $nullableFields
     * @todo might have bugs
     * @return array
     */
    public static function groupNullableColumnArray(array $arrayToGroup, array $nullableFields): array
    {
        $arrayAfterGroup = [];
        foreach ($arrayToGroup as $item) {
            $nullValueFields = array_map(fn() => false, array_flip($nullableFields));
            foreach ($nullValueFields as $nullableFieldName => $isNull) {
                $nullValueFields[$nullableFieldName] = ($item[$nullableFieldName] ?? null) === null;
            }
            $nullValueFieldsCount = array_sum($nullValueFields); // counts all null value fields
            if ($nullValueFieldsCount === \count($nullValueFields)) {
                $arrayAfterGroup['allNull'][] = $item;
            } elseif ($nullValueFieldsCount === 0) {
                $arrayAfterGroup['notAllNull'][] = $item;
            } else {
                $nullValueFieldName = implode('+', array_keys($nullValueFields, true)); // if there's multi fields having null value, we should group them together
                $arrayAfterGroup[$nullValueFieldName][] = $item;
            }
        }

        return $arrayAfterGroup;
    }

    public static function getUpdateFieldsWithoutExpected(array $updateItem, \Illuminate\Database\Eloquent\Model $model): array
    {
        return array_diff(array_keys($updateItem), $model->updateExpectFields);
    }

    protected function profileWebRequestStopped ($webRequestTimer): void
    {
        $webRequestTimer->stop();
        $this->profiles['webRequestTimes']++;
        $this->profiles['webRequestTiming'] += $webRequestTimer->getTiming();
    }

    protected function getGuzzleHttpPoolConfig ($webRequestTimer): array
    {
        return [
            'concurrency' => 10,
            'fulfilled' => function (\Psr\Http\Message\ResponseInterface $response, int $index) use ($webRequestTimer) {
                $this->profileWebRequestStopped($webRequestTimer);
                ExceptionAdditionInfo::set(['parsingPage' => $index]);
                $this->checkThenParsePostsInfo(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
                $webRequestTimer->start(); // resume timing for possible succeed web request
            },
            'rejected' => function (\GuzzleHttp\Exception\RequestException $e, int $index) {
                ExceptionAdditionInfo::set(['parsingPage' => $index]);
                report($e);
            }
        ];
    }

    protected function cacheIndexesAndUsersInfo ($indexesInfo, $usersInfo): void
    {
        $this->indexesInfo = array_merge($this->indexesInfo, $indexesInfo);
        $this->profiles['parsedUserTimes'] = $this->usersInfo->parseUsersInfo($usersInfo);
    }

    protected function saveIndexesAndUsersInfo ($chunkInsertBufferSize): void
    {
        $indexModel = new IndexModel();
        $indexUpdateFields = static::getUpdateFieldsWithoutExpected($this->indexesInfo[0], $indexModel);
        $indexModel->chunkInsertOnDuplicate($this->indexesInfo, $indexUpdateFields, $chunkInsertBufferSize);
        $this->usersInfo->saveUsersInfo();
    }

    protected function cachePageInfoAndTrimEndPage ($pageInfo): void
    {
        $this->pageInfo = $pageInfo;
        $totalPages = $pageInfo['total_page'];
        if ($this->endPage > $totalPages) { // crawl end page should be trimmed when it's larger than replies total page
            $this->endPage = $totalPages;
        }
    }
}
