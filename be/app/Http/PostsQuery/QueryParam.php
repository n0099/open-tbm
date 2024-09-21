<?php

namespace App\Http\PostsQuery;

class QueryParam
{
    public string $name;

    public array|string|int $value;

    protected array $subParams;

    public function __construct(array $param)
    {
        $this->name = (string) array_keys($param)[0];
        $this->value = $param[$this->name];
        array_shift($param);
        $this->subParams = $param;
    }

    public function getAllSub(): array
    {
        return $this->subParams;
    }

    public function getSub(string $name)
    {
        return $this->subParams[$name] ?? null;
    }

    public function setSub(string $name, array|string|int $value): void
    {
        $this->subParams[$name] = $value;
    }
}
