<?php

namespace App\Jobs;

class CrawlerQueue
{
    public $tries = 5;

    protected $queueDeleteAfter = '-5 mins';

    protected static function convertIDListKey(array $list, string $keyName): array
    {
        $newList = [];

        foreach ($list as $item) {
            $newList[$item[$keyName]] = $item;
        }
        ksort($newList);

        return $newList;
    }
}
