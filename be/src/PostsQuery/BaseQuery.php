<?php

namespace App\PostsQuery;

abstract readonly class BaseQuery
{
    private string $orderByField;

    private bool $orderByDesc;

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
        return $this->orderByDesc;
    }

    protected function setOrderByDesc(bool $value): self
    {
        $this->orderByDesc = $value;
        return $this;
    }
}
