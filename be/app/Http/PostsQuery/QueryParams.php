<?php

namespace App\Http\PostsQuery;

use App\Helper;

class QueryParams
{
    /**
     * @var Param[]
     */
    protected array $params;

    /**
     * @param array[] $params
     */
    public function __construct(array $params)
    {
        $this->params = array_map(static fn ($p) => new Param($p), $params);
    }

    public function count(): int
    {
        return count($this->params);
    }

    /**
     * @return Param[]
     */
    public function filter(string ...$names): array
    {
        // array_values() will reset keys
        return array_values(array_filter(
            $this->params,
            static fn ($p): bool => \in_array($p->name, $names, true)
        ));
    }

    /**
     * @return Param[]
     */
    public function omit(string ...$names): array
    {
        return array_values(array_filter(
            $this->params,
            static fn ($p): bool => !\in_array($p->name, $names, true)
        ));
    }

    public function getUniqueParamValue(string $name): mixed
    {
        return $this->filter($name)[0]->value ?? null;
    }

    public function setUniqueParamValue(string $name, mixed $value): void
    {
        $paramsMatchWithName = array_keys(array_filter($this->params, static fn ($p) => $p->name === $name));
        if ($paramsMatchWithName === []) {
            throw new \RuntimeException('Cannot find param with given param name');
        }
        $this->params[$paramsMatchWithName[0]]->value = $value;
    }

    public function addDefaultValueOnUniqueParams(): void
    {
        $uniqueParamsDefaultValue = [
            'postTypes' => ['value' => Helper::POST_TYPES],
            'orderBy' => ['value' => 'default', 'subParam' => ['direction' => 'default']]
        ];
        foreach ($uniqueParamsDefaultValue as $name => $value) {
            // add unique params with default value when it's not presented in $this->params
            if ($this->getUniqueParamValue($name) === null) {
                $this->params[] = new Param([
                    $name => $value['value'],
                    'subParam' => $value['subParam'] ?? []
                ]);
            }
        }
    }

    public function addDefaultValueOnParams(): void
    {
        $paramDefaultValueByType = [
            'numeric' => ['range' => '='],
            'text' => ['matchBy' => 'implicit', 'spaceSplit' => false]
        ];
        $paramsNameByType = [
            'numeric' => [
                'tid',
                'pid',
                'spid',
                'threadViewNum',
                'threadShareNum',
                'threadReplyNum',
                'replySubReplyNum',
                'authorUid',
                'authorExpGrade',
                'latestReplierUid'
            ],
            'text' => [
                'threadTitle',
                'postContent',
                'authorName',
                'authorDisplayName',
                'latestReplierName',
                'latestReplierDisplayName'
            ]
        ];
        $subParamsDefaultValue = collect($paramsNameByType)->flatMap(static fn (array $names, string $type) =>
            array_fill_keys($names, $paramDefaultValueByType[$type]))->toArray();
        foreach ($this->params as $param) { // set sub params with default value
            foreach ($subParamsDefaultValue[$param->name] ?? [] as $name => $value) {
                if ($param->getSub($name) === null) {
                    $param->setSub($name, $value);
                }
            }
        }
    }
}
