<?php

namespace App\DTO\Post;

use Symfony\Component\Serializer\Attribute\Ignore;

trait Post
{
    // public bool $isMatchQuery; // https://github.com/php/php-src/issues/18391
    #[Ignore] public mixed $sortingKey = null;
}
