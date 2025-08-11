<?php

namespace App\DTO\Post;

use App\Entity\Post\Reply as ReplyEntity;
use Illuminate\Support\Collection;
use Symfony\Component\Serializer\Attribute\Ignore;

class Reply extends ReplyEntity implements SortablePost
{
    use Post;
    use PostWithContent;

    #[Ignore] public int $fid;
    public Collection $subReplies;
    public bool $isMatchQuery; // https://github.com/php/php-src/issues/18391
}
