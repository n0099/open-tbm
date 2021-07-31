<?php

namespace App\Jobs\Crawler;

use Illuminate\Contracts\Queue\ShouldQueue;

abstract class CrawlerQueue implements ShouldQueue
{
    public int $tries = 5;

    protected int $fid;

    protected int $startPage;

    protected string $queueDeleteAfter = '-5 mins';
}
