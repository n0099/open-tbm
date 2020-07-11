<?php

namespace App\Jobs\Crawler;

class CrawlerQueue
{
    public $tries = 5;

    protected $fid;

    protected $queueDeleteAfter = '-5 mins';
}
