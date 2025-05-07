<?php

namespace App\DTO\PostKey;

abstract readonly class PostKeyWithParent extends BasePostKey
{
    public function __construct(
        public int $parentPostId,
        int $postId,
        string $orderByFieldName,
        mixed $orderByFieldValue,
    ) {
        parent::__construct($postId, $orderByFieldName, $orderByFieldValue);
    }
}
