<?php

namespace App\Jobs\Crawler;

class CrawlerQueue
{
    public $tries = 5;

    protected $forumID;

    protected $queueDeleteAfter = '-5 mins';
}
