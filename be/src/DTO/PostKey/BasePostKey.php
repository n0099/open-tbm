<?php

namespace App\DTO\PostKey;

abstract readonly class BasePostKey
{
    public function __construct(
        public int $fid,
        public int $postId,
        public string $orderByFieldName,
        public mixed $orderByFieldValue,
    ) {}
}
