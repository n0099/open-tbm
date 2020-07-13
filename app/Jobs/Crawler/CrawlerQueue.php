<?php

namespace App\Jobs\Crawler;

class CrawlerQueue
{
    public int $tries = 5;

    protected int $fid;

    protected int $startPage;

    protected string $queueDeleteAfter = '-5 mins';
}
