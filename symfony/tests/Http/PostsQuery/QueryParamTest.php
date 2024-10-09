<?php

namespace App\Tests\Http\PostsQuery;

use App\Http\PostsQuery\QueryParam;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryParam::class)]
class QueryParamTest extends TestCase
{
    public static function provide(): array
    {
        return [
            ['mainParam', 0, ['subParam1' => '1', 'subParam2' => ['nest' => '2']], mt_rand()],
        ];
    }

    #[DataProvider('provide')]
    public function test(string $name, array|string|int $value, array $subParams, int $randomValue): void
    {
        $sut = new QueryParam([$name => $value, ...$subParams]);
        self::assertEquals($name, $sut->name);
        self::assertEquals($value, $sut->value);
        self::assertEquals($subParams, $sut->getAllSub());
        foreach ($subParams as $subParamName => $subParamValue) {
            self::assertEquals($subParamValue, $sut->getSub($subParamName));
            $sut->setSub($subParamName, $randomValue);
            self::assertEquals($randomValue, $sut->getSub($subParamName));
        }
    }

    #[DataProvider('provide')]
    public function testTooManyNesting(string $name, array|string|int $value, array $subParams): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new QueryParam([[$name => $value, ...$subParams]]);
    }
}
