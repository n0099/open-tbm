<?php

namespace App\Eloquent\Model\Post\Content;

use App\Eloquent\Model\Post\Post;
use App\Eloquent\ModelAttributeMaker;
use Illuminate\Database\Eloquent\Casts\Attribute;
use TbClient\Wrapper\PostContentWrapper;

/** @property string $protoBufBytes */
abstract class PostContent extends Post
{
    protected function protoBufBytes(): Attribute
    {
        return ModelAttributeMaker::makeProtoBufAttribute(PostContentWrapper::class);
    }
}
