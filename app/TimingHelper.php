<?php

namespace App;

class TimingHelper
{
    private float $startTime = 0;

    private float $stopTime = 0;

    public function __construct()
    {
        $this->start();
    }

    public function start(): void
    {
        $this->startTime = microtime(true);
    }

    public function stop(): void
    {
        $this->stopTime = microtime(true);
    }

    public function getTiming(): float
    {
        return $this->stopTime - $this->startTime;
    }
}
