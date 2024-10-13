<?php

namespace App\DTO\PostKey;

abstract readonly class PostKeyWithParent extends BasePostKey
{
    public function __construct(
        public int $parentPostId,
        public int $postId,
        public string $orderByFieldName,
        public mixed $orderByFieldValue,
    ) {}
}
