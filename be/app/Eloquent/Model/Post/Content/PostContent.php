<?php

namespace App\Eloquent\Model\Post\Content;

use App\Eloquent\Model\Post\Post;
use App\Eloquent\ModelHasResourceAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property string $protoBufBytes
 */
abstract class PostContent extends Post
{
    use ModelHasResourceAttribute;

    protected function protoBufBytes(): Attribute
    {
        return $this->resourceAttribute();
    }
}
