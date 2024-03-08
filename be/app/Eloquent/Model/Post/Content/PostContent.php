<?php

namespace App\Eloquent\Model\Post\Content;

use App\Eloquent\Model\Post\Post;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property string $protoBufBytes
 */
abstract class PostContent extends Post
{
    protected function protoBufBytes(): Attribute
    {
        return Attribute::make(
            /**
             * @param resource|null $value
             * @return string
             */
            get: static fn ($value) => $value === null ? null : stream_get_contents($value)
        )->shouldCache();
    }
}
