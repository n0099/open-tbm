<?php

namespace App\Tests\PostsQuery;

use App\Helper;
use App\PostsQuery\QueryParam;
use App\PostsQuery\QueryParams;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryParams::class)]
class QueryParamsTest extends TestCase
{
    #[DataProvider('provide')]
    public function test(array $rawParams, int $randomValue): void
    {
        $sut = new QueryParams($rawParams);
        $params = array_map(static fn(array $p) => new QueryParam($p), $rawParams);
        self::assertEquals(count($params), $sut->count());
        self::assertEquals($params, $sut->pick('mainParam'));
        self::assertEquals([], $sut->pick());
        self::assertEquals([], $sut->omit('mainParam'));
        self::assertEquals($params[0]->value, $sut->getUniqueParamValue('mainParam'));
        $sut->setUniqueParamValue('mainParam', $randomValue);
        self::assertEquals($randomValue, $sut->getUniqueParamValue('mainParam'));
    }

    public static function provide(): array
    {
        return [[
            [
                ['mainParam' => 0, 'subParam1' => '1', 'subParam2' => ['nest' => '2']],
                ['mainParam' => 0, 'subParam1' => '1', 'subParam2' => ['nest' => '2']],
            ],
            mt_rand(),
        ]];
    }

    #[DataProvider('provideAddDefaultValueOnUniqueParams')]
    public function testAddDefaultValueOnUniqueParams(array $params, array $expected): void
    {
        $sut = new QueryParams($params);
        $sut->addDefaultValueOnUniqueParams();
        self::assertEquals(new QueryParams($expected), $sut);
    }

    public static function provideAddDefaultValueOnUniqueParams(): array
    {
        return [[
            [['orderBy' => 'test']],
            [
                ['orderBy' => 'test', 'direction' => 'ASC'],
                ['postTypes' => Helper::POST_TYPES],
            ],
        ]];
    }

    #[DataProvider('provideAddDefaultValueOnParams')]
    public function testAddDefaultValueOnParams(array $params, array $expected): void
    {
        $sut = new QueryParams($params);
        $sut->addDefaultValueOnParams();
        self::assertEquals(new QueryParams($expected), $sut);
    }

    public static function provideAddDefaultValueOnParams(): array
    {
        return collect(QueryParams::PARAM_NAME_KEY_BY_TYPE)
            ->flatMap(static fn(array $names, string $type) =>
                array_map(static fn(string $name) => [$type, $name], $names))
            ->mapWithKeys(static fn(array $typeAndName) => [$typeAndName[1] => $typeAndName])
            ->map(static function (array $typeAndName) {
                /** @var 'numeric' | 'text' $name */
                /** @var string $name */
                [$type, $name] = $typeAndName;
                $param = [
                    $name => match ($type) {
                        'numeric' => 0,
                        'text' => 'test',
                        default => throw new \Exception(),
                    },
                ];
                return [
                    [$param],
                    [[...$param, ...QueryParams::PARAM_DEFAULT_VALUE_KEY_BY_TYPE[$type]]],
                ];
            })
            ->all();
    }
}
