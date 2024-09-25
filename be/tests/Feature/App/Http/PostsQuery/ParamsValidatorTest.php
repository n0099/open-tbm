<?php

namespace Tests\Feature\App\Http\PostsQuery;

use App\Helper;
use App\Http\PostsQuery\ParamsValidator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(ParamsValidator::class)]
class ParamsValidatorTest extends TestCase
{
    public static function newParamsValidator(array $rawParams): ParamsValidator
    {
        return new ParamsValidator(App::make(Factory::class), $rawParams);
    }

    #[DataProvider('provideValidate40001')]
    #[DataProvider('provideValidate40005')]
    #[DataProvider('provideValidate40003')]
    #[DataProvider('provideValidate40004')]
    public function testValidate(int $errorCode, array $params): void
    {
        try {
            $sut = self::newParamsValidator($params);
            $sut->addDefaultParamsThenValidate(shouldSkip40003: false);
        } catch (HttpResponseException $e) {
            self::assertEquals($errorCode, \Safe\json_decode($e->getResponse()->getContent())->errorCode);
        }
    }

    public static function provideValidate40001(): array
    {
        $uniqueParams = [['postTypes' => ['thread']], ['orderBy' => 'postedAt']];
        return collect([[$uniqueParams[0]], [$uniqueParams[1]], $uniqueParams])
            ->mapWithKeys(static function (array $params) {
                $keys = implode(',', array_map(static fn(array $param) => array_key_first($param), $params));
                return ["40001, $keys" => [40001, $params]];
            })->all();
    }

    public static function provideValidate40005(): array
    {
        return collect([['fid' => 0], ['postTypes' => ['thread']], ['orderBy' => 'postedAt']])
            ->mapWithKeys(static fn(array $p) => [
                '40005, ' . array_key_first($p) => [40005, [['tid' => 0], $p, $p]],
            ])
            ->all();
    }

    public static function provideValidate40003(): array
    {
        return collect(ParamsValidator::REQUIRED_POST_TYPES_KEY_BY_PARAM_NAME)
            ->map(static fn(array $coverageAndPostTypes, string $name) => [
                [$name => match ($name) {
                    'latestReplyPostedAt' => '2024-01-01,2024-01-01',
                    'threadProperties' => ['sticky'],
                    default => '0',
                }],
                ['postTypes' => array_diff(Helper::POST_TYPES, $coverageAndPostTypes[1])],
            ])
            ->mapWithKeys(static fn(array $i, string $name) => ["40003, $name" => [40003, $i]])
            ->all();
    }

    public static function provideValidate40004(): array
    {
        return collect(ParamsValidator::REQUIRED_POST_TYPES_KEY_BY_ORDER_BY_VALUE)
            ->map(static fn(array $coverageAndPostTypes, string $name) => [
                ['tid' => 0],
                ['postTypes' => array_diff(Helper::POST_TYPES, $coverageAndPostTypes[1])],
                ['orderBy' => $name],
            ])
            ->mapWithKeys(static fn(array $i, string $name) => ["40004, $name" => [40004, $i]])
            ->all();
    }

    #[DataProvider('providerDateRangeParamValueOrder')]
    public function testDateRangeParamValueOrder(array $params): void
    {
        $this->assertThrows(
            fn() => self::newParamsValidator($params),
            ValidationException::class,
            'The 0 field must be a date before or equal to 1.',
        );
    }

    public static function providerDateRangeParamValueOrder(): array
    {
        $paramNames = ['postedAt', 'latestReplyPostedAt'];
        return array_map(static fn(string $name) => [[[$name => '2024-01-02,2024-01-01']]], $paramNames);
    }

    #[DataProvider('providePostTypesParamValueOrder')]
    public function testPostTypesParamValueOrder(array $orderedPostTypes, $shuffledPostTypes): void
    {
        $sut = self::newParamsValidator([['postTypes' => $shuffledPostTypes], ['tid' => 0]]);
        $sut->addDefaultParamsThenValidate(shouldSkip40003: true);
        self::assertEquals($orderedPostTypes, $sut->params->getUniqueParamValue('postTypes'));
    }

    public static function providePostTypesParamValueOrder(): array
    {
        return [[
            collect(Helper::POST_TYPES)->sort()->values()->all(),
            Arr::shuffle(Helper::POST_TYPES),
        ]];
    }
}
