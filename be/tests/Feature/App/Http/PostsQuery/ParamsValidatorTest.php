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
    public function testValidate(array $params): void
    {
        $this->assertThrows(fn() => self::newParamsValidator($params), HttpResponseException::class);
    }

    public static function provideValidate40001(): array
    {
        $uniqueParams = [['postTypes' => ['thread']], ['orderBy' => 'postedAt']];
        return [[[$uniqueParams[0]]], [[$uniqueParams[1]]], [$uniqueParams]];
    }

    public static function provideValidate40005(): array
    {
        $uniqueParams = [['fid' => 0], ['postTypes' => ['thread']], ['orderBy' => 'postedAt']];
        return array_map(static fn(array $p) => [[$p, $p]], $uniqueParams);
    }

    #[DataProvider('validate40003Provider')]
    #[DataProvider('validate40004Provider')]
    public function testAddDefaultParamsThenValidate(array $params): void
    {
        $this->assertThrows(function () use ($params) {
            $sut = self::newParamsValidator($params);
            $sut->addDefaultParamsThenValidate(shouldSkip40003: false);
        }, HttpResponseException::class);
    }

    public static function validate40003Provider(): array
    {
        return [[[['postTypes' => ['thread']], ['spid' => '0']]]];
    }

    public static function validate40004Provider(): array
    {
        return [[[['postTypes' => ['thread']], ['orderBy' => 'spid']]]];
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
