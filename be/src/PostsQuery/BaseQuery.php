<?php

namespace App\PostsQuery;

abstract readonly class BaseQuery
{
    private string $orderByField;

    private bool $isOrderByDesc;

    public function __construct(
        public QueryResult $queryResult,
        public PostsTree $postsTree,
    ) {}

    abstract public function query(QueryParams $params, ?string $cursor): void;

    public function getOrderByField(): string
    {
        return $this->orderByField;
    }

    protected function setOrderByField(string $value): self
    {
        $this->orderByField = $value;
        return $this;
    }

    public function isOrderByDesc(): bool
    {
        return $this->isOrderByDesc;
    }

    protected function setIsOrderByDesc(bool $value): self
    {
        $this->isOrderByDesc = $value;
        return $this;
    }
}
