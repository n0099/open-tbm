<?php

namespace App\Tieba\Crawler;

abstract class Crawlable
{
    protected int $fid;

    protected string $clientVersion;

    protected array $indexesInfo = [];

    protected array $profiles = [
        'webRequestTiming' => 0,
        'savePostsTiming' => 0,
        'webRequestTimes' => 0,
        'parsedPostTimes' => 0,
        'parsedUserTimes' => 0,
    ];

    protected array $pagesInfo = [];

    public int $startPage;

    public int $endPage;

    abstract public function doCrawl();

    abstract public function savePostsInfo();

    abstract protected function checkThenParsePostsInfo(array $responseJson): void;

    public function getPages() : array
    {
        return $this->pagesInfo;
    }

    public function getProfiles(): array
    {
        return $this->profiles;
    }

    protected function getClientHelper(): ClientRequester
    {
        /* enable guzzle laravel debugbar
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
            //'handler' => $stack,
            'client_version' => $this->clientVersion,
            'request.options' => [
                'timeout' => 5,
                'connect_timeout' => 5
            ]
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
                $nullValueFields[$nullableFieldName] = $item[$nullableFieldName] ?? null === null;
            }
            $nullValueFieldsCount = array_sum($nullValueFields); // counts all null value fields
            if ($nullValueFieldsCount == count($nullValueFields)) {
                $arrayAfterGroup['allNull'][] = $item;
            } elseif ($nullValueFieldsCount == 0) {
                $arrayAfterGroup['notAllNull'][] = $item;
            } else {
                $nullValueFieldName = implode('+', array_keys($nullValueFields, true)); // if there's multi fields having null value, we should group them together
                $arrayAfterGroup[$nullValueFieldName][] = $item;
            }
        }

        return $arrayAfterGroup;
    }

    public static function getUpdateFieldsWithoutExpected(array $updateItem, $model): array
    {
        return array_diff(array_keys($updateItem), $model->updateExpectFields);
    }
}
