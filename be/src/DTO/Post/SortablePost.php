<?php

namespace App\DTO\Post;

interface SortablePost
{
    public bool $isMatchQuery { get; set; }
    public mixed $sortingKey { get; set; }
}
