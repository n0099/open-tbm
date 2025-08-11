<?php

namespace App\PostsQuery;

class QueryParam
{
    public readonly string $name;

    public array|string|int $value;

    private(set) array $subParams;

    public function __construct(array $param)
    {
        $this->name = (string) array_key_first($param);
        if (is_numeric($this->name)) {
            throw new \InvalidArgumentException();
        }
        $this->value = $param[$this->name];
        $this->subParams = array_slice($param, 1);
    }

    public function getSub(string $name)
    {
        return $this->subParams[$name] ?? null;
    }

    public function setSub(string $name, array|string|int $value): self
    {
        $this->subParams[$name] = $value;
        return $this;
    }
}
