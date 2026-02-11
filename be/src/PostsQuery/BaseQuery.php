<?php

namespace App\PostsQuery;

abstract readonly class BaseQuery
{
    public private(set) string $orderByField;
    public private(set) bool $isOrderByDesc;

    public function __construct(
        public QueryResult $queryResult,
        public PostsTree $postsTree,
    ) {}

    abstract public function query(QueryParams $params, ?string $cursor): void;

    // https://wiki.php.net/rfc/property-hooks#interaction_with_readonly
    // https://externals.io/message/124149
    protected function setOrderByField(string $value): self
    {
        $this->orderByField = $value;
        return $this;
    }

    protected function setIsOrderByDesc(bool $value): self
    {
        $this->isOrderByDesc = $value;
        return $this;
    }
}
