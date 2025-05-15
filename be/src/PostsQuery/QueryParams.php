<?php

namespace App\PostsQuery;

use App\Helper;
use Illuminate\Support\Arr;

class QueryParams
{
    /** @var QueryParam[] */
    private array $params;

    /** @param array[] $params */
    public function __construct(array $params)
    {
        $this->params = array_map(static fn(array $p) => new QueryParam($p), $params);
    }

    /** @psalm-return int<0, max> */
    public function count(): int
    {
        return count($this->params);
    }

    /**
     * @return QueryParam[]
     * @psalm-return list<QueryParam>
     */
    public function pick(string ...$names): array
    {
        // array_values() will reset keys
        return array_values(array_filter(
            $this->params,
            static fn($p): bool => \in_array($p->name, $names, true),
        ));
    }

    /**
     * @return QueryParam[]
     * @psalm-return list<QueryParam>
     */
    public function omit(string ...$names): array
    {
        return array_values(array_filter(
            $this->params,
            static fn($p): bool => !\in_array($p->name, $names, true),
        ));
    }

    /**
     * @return QueryParam[]
     * @psalm-return list<QueryParam>
     */
    public function getAll(): array
    {
        return $this->params;
    }

    public function getUniqueParamValue(string $name): mixed
    {
        return $this->pick($name)[0]->value ?? null;
    }

    public function setUniqueParamValue(string $name, mixed $value): self
    {
        $this->params[$this->getParamsIndexByName($name)[0]]->value = $value;
        return $this;
    }

    /** @return int[] */
    protected function getParamsIndexByName(string $name): array
    {
        return array_keys(array_filter($this->params, static fn($p) => $p->name === $name));
    }

    /** @SuppressWarnings(PHPMD.ElseExpression) */
    public function addDefaultValueOnUniqueParams(): void
    {
        $uniqueParamsDefaultValue = [
            'postTypes' => ['value' => Helper::POST_TYPES],
            'orderBy' => ['value' => 'default', 'subParam' => ['direction' => 'ASC']],
        ];
        foreach ($uniqueParamsDefaultValue as $name => $default) {
            // add unique params with default value when it's not presented in $this->params
            $value = $this->getUniqueParamValue($name) ?? $default['value'];
            $subParams = [
                ...$default['subParam'] ?? [],
                ...Arr::first($this->pick($name))?->getAllSub() ?? []
            ];
            $paramFilledWithDefaults = new QueryParam([$name => $value, ...$subParams]);

            $paramsIndex = $this->getParamsIndexByName($name);
            if ($paramsIndex === []) {
                $this->params[] = $paramFilledWithDefaults;
            } else {
                $this->params[$paramsIndex[0]] = $paramFilledWithDefaults;
            }
        }
    }

    public const array PARAM_DEFAULT_VALUE_KEY_BY_TYPE = [
        'numeric' => ['range' => '='],
        'text' => ['matchBy' => 'explicit', 'spaceSplit' => false],
    ];

    public const array PARAM_NAME_KEY_BY_TYPE = [
        'numeric' => [
            'tid',
            'pid',
            'spid',
            'threadViewCount',
            'threadShareCount',
            'threadReplyCount',
            'replySubReplyCount',
            'authorUid',
            'authorExpGrade',
            'latestReplierUid',
        ],
        'text' => [
            'threadTitle',
            'postContent',
            'authorName',
            'authorDisplayName',
            'latestReplierName',
            'latestReplierDisplayName',
        ],
    ];

    public function addDefaultValueOnParams(): void
    {
        $subParamsDefaultValue = collect(self::PARAM_NAME_KEY_BY_TYPE)
            ->mapWithKeys(static fn(array $names, string $type) =>
                array_fill_keys($names, self::PARAM_DEFAULT_VALUE_KEY_BY_TYPE[$type]));
        foreach ($this->params as $param) { // set sub params with default value
            foreach ($subParamsDefaultValue->get($param->name, []) as $name => $value) {
                if ($param->getSub($name) === null) {
                    $param->setSub($name, $value);
                }
            }
        }
    }
}
