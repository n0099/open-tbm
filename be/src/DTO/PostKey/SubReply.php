<?php

namespace App\DTO\PostKey;

readonly class SubReply extends PostKeyWithParent
{
    public function __construct(
        public int $tid,
        public int $parentPostId,
        public int $postId,
        public string $orderByFieldName,
        public mixed $orderByFieldValue,
    ) {
        parent::__construct($this->parentPostId, $this->postId, $this->orderByFieldName, $this->orderByFieldValue);
    }
}
