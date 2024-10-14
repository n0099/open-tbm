<?php

namespace App\PostsQuery;

abstract readonly class BaseQuery
{
    public string $orderByField;

    public bool $orderByDesc;

    public function __construct(
        public QueryResult $queryResult,
        public PostsTree $postsTree,
    ) {}

    abstract public function query(QueryParams $params, ?string $cursor): void;

    protected function setOrderByField(string $orderByField): void
    {
        $this->orderByField = $orderByField;
    }

    protected function setOrderByDesc(bool $orderByDesc): void
    {
        $this->orderByDesc = $orderByDesc;
    }
}
