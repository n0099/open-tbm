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
    public function pick(string ...$names): array
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
        return $this->pick($name)[0]->value ?? null;
    }

    public function setUniqueParamValue(string $name, mixed $value): void
    {
        $this->params[$this->getParamsIndexByName($name)[0]]->value = $value;
    }

    /**
     * @return int[]
     */
    protected function getParamsIndexByName(string $name): array
    {
        return array_keys(array_filter($this->params, static fn ($p) => $p->name === $name));
    }

    public function addDefaultValueOnUniqueParams(): void
    {
        $uniqueParamsDefaultValue = [
            'postTypes' => ['value' => Helper::POST_TYPES],
            'orderBy' => ['value' => 'default', 'subParam' => ['direction' => 'ASC']]
        ];
        foreach ($uniqueParamsDefaultValue as $name => $value) {
            // add unique params with default value when it's not presented in $this->params
            $paramFilledWithDefaults = new Param(array_merge(
                [$name => $this->getUniqueParamValue($name) ?? $value['value']],
                $this->pick($name)[0]->subParam ?? $value['subParam'] ?? []
            ));
            $paramsIndex = $this->getParamsIndexByName($name);
            if ($paramsIndex === []) {
                $this->params[] = $paramFilledWithDefaults;
            } else {
                $this->params[$paramsIndex[0]] = $paramFilledWithDefaults;
            }
        }
    }

    public function addDefaultValueOnParams(): void
    {
        $paramDefaultValueByType = [
            'numeric' => ['range' => '='],
            'text' => ['matchBy' => 'explicit', 'spaceSplit' => false]
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
