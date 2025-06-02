<?php

namespace App\DTO\PostKey;

abstract readonly class PostKeyWithParent extends BasePostKey
{
    public function __construct(
        int $fid,
        public int $parentPostId,
        int $postId,
        mixed $orderByFieldValue,
    ) {
        parent::__construct($fid, $postId, $orderByFieldValue);
    }
}
