<?php

namespace App\DTO\Post;

use App\Entity\Post\Thread as ThreadEntity;
use Illuminate\Support\Collection;

class Thread extends ThreadEntity implements SortablePost
{
    use Post;

    public Collection $replies;
    public bool $isMatchQuery; // https://github.com/php/php-src/issues/18391
}
