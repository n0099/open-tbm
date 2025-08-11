<?php

namespace App\DTO\Post;

use App\Entity\Post\SubReply as SubReplyEntity;
use Symfony\Component\Serializer\Attribute\Ignore;

class SubReply extends SubReplyEntity implements SortablePost
{
    use Post;
    use PostWithContent;

    #[Ignore] public int $fid;
    #[Ignore] public bool $isMatchQuery { get => true; set {} }
}
