<?php

namespace App\Tieba\Crawler;

use function GuzzleHttp\json_encode;

abstract class Crawlable
{
    protected $forumID;

    protected $clientVersion;

    protected $indexesList = [];

    protected $webRequestTimes = 0;

    protected $parsedPostTimes = 0;

    protected $parsedUserTimes = 0;

    abstract public function doCrawl();

    abstract public function saveLists();

    public function getTimes(): array
    {
        return [
            'webRequestTimes' => $this->webRequestTimes,
            'parsedPostTimes' => $this->parsedPostTimes,
            'parsedUserTimes' => $this->parsedUserTimes
        ];
    }

    protected function getClientHelper(): ClientRequester
    {
        /*$debugBar = resolve('debugbar');

        $timeline = $debugBar->getCollector('time');
        $profiler = new GuzzleHttp\Profiling\Debugbar\Profiler($timeline);

        $stack = GuzzleHttp\HandlerStack::create();
        $stack->unshift(new GuzzleHttp\Profiling\Middleware($profiler));

        $logger = $debugBar->getCollector('messages');
        $stack->push(GuzzleHttp\Middleware::log($logger, new GuzzleHttp\MessageFormatter()));
        $stack->push(GuzzleHttp\Middleware::log(\Log::getLogger(), new GuzzleHttp\MessageFormatter('{code} {host}{target} {error}')));*/

        return new ClientRequester([
            //'handler' => $stack,
            'client_version' => $this->clientVersion,
            'request.options' => [
                'timeout' => 5,
                'connect_timeout' => 5
            ]
        ]);
    }

    public static function nullableValidate($value, bool $isJson = false)
    {
        if ($value === '""' || $value === '[]' || blank($value)) {
            return null;
        }

        return $isJson ? json_encode($value) : $value;
    }

    public static function getArrayValuesByKeys(array $haystack, array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $haystack[$key];
        }
        return $values;
    }

    /**
     * Group INSERT sql statement to prevent updating cover old data with null values
     *
     * @param array $arrayToGroup
     * @param array $nullableFields
     *
     * @return array
     */
    public static function groupNullableColumnArray(array $arrayToGroup, array $nullableFields): array
    {
        $arrayAfterGroup = [];
        foreach ($arrayToGroup as $item) {
            $nullValueFields = array_map(function () {
                return false;
            }, array_flip($nullableFields));
            foreach ($nullValueFields as $nullableFieldName => $isNull) {
                $nullValueFields[$nullableFieldName] = $item[$nullableFieldName] ?? null === null;
            }
            $nullValueFieldsCount = array_sum($nullValueFields); // counts all null value fields
            if ($nullValueFieldsCount == count($nullValueFields)) {
                $arrayAfterGroup['allNull'][] = $item;
            } elseif ($nullValueFieldsCount == 0) {
                $arrayAfterGroup['notAllNull'][] = $item;
            } else {
                $nullValueFieldName = implode(array_keys($nullValueFields, true), '+'); // if there's multi fields having null value, we should group them together
                $arrayAfterGroup[$nullValueFieldName][] = $item;
            }
        }

        return $arrayAfterGroup;
    }
}
