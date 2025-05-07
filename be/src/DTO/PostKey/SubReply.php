<?php

namespace App\DTO\PostKey;

readonly class SubReply extends PostKeyWithParent
{
    public function __construct(
        public int $tid,
        int $parentPostId,
        int $postId,
        string $orderByFieldName,
        mixed $orderByFieldValue,
    ) {
        parent::__construct($parentPostId, $postId, $orderByFieldName, $orderByFieldValue);
    }
}
