<?php

namespace App\DTO\PostKey;

readonly class SubReply extends PostKeyWithParent
{
    public function __construct(
        int $fid,
        public int $tid,
        int $parentPostId,
        int $postId,
        mixed $orderByFieldValue,
    ) {
        parent::__construct($fid, $parentPostId, $postId, $orderByFieldValue);
    }
}
