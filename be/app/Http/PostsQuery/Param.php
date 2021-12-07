<?php

namespace App\Http\PostsQuery;

class Param
{
    public string $name;

    public mixed $value;

    protected array $subParams;

    public function getSubParams(): array
    {
        return $this->subParams;
    }

    public function __construct(array $param)
    {
        $this->name = (string)array_keys($param)[0];
        $this->value = $param[$this->name];
        array_shift($param);
        $this->subParams = $param;
    }

    public function __get(string $name)
    {
        return $this->subParams[$name];
    }

    public function __set(string $name, mixed $value)
    {
        $this->subParams[$name] = $value;
    }

    public function __isset(string $name)
    {
        return isset($this->subParams[$name]);
    }
}
