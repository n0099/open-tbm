<?php

namespace Tests\Feature\App;

use App\Helper;
use Illuminate\Http\Exceptions\HttpResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\String\UnicodeString;
use Tests\TestCase;

#[CoversClass(Helper::class)]
class HelperTest extends TestCase
{
    public static function assertAbort(int $statusCode, int $errorCode, string $errorInfo, callable $callback): void
    {
        try {
            $callback();
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();
            self::assertEquals($statusCode, $response->getStatusCode());
            $responseContent = \Safe\json_decode($response->getContent());
            self::assertEquals($errorCode, $responseContent->errorCode);
            self::assertEquals($errorInfo, $responseContent->errorInfo);
        }
    }

    #[DataProvider('provideAbortAPI')]
    public function testAbortAPI(int $statusCode, int $errorCode, string $errorInfo): void
    {
        self::assertAbort($statusCode, $errorCode, $errorInfo, static fn() => Helper::abortAPI($errorCode));
    }

    #[DataProvider('provideAbortAPI')]
    public function testAbortAPIIf(int $statusCode, int $errorCode, string $errorInfo): void
    {
        self::assertAbort($statusCode, $errorCode, $errorInfo, static fn() => Helper::abortAPIIf($errorCode, true));
    }

    #[DataProvider('provideAbortAPI')]
    public function testAbortAPIIfNot(int $statusCode, int $errorCode, string $errorInfo): void
    {
        self::assertAbort($statusCode, $errorCode, $errorInfo, static fn() => Helper::abortAPIIfNot($errorCode, false));
    }

    public static function provideAbortAPI(): array
    {
        return collect(Helper::ERROR_STATUS_CODE_INFO)
            ->flatMap(static fn(array $codeWithInfo, int $statusCode) => array_map(
                static fn(int $errorCode, string $errorInfo) => [$statusCode, $errorCode, $errorInfo],
                array_keys($codeWithInfo),
                $codeWithInfo,
            ))
            ->all();
    }

    #[DataProvider('provideAbortAPIWithNonExistsCode')]
    public function testAbortAPIWithNonExistsCode(int $errorCode): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Given error code doesn\'t existed'));
        Helper::abortAPI($errorCode);
    }

    public static function provideAbortAPIWithNonExistsCode(): array
    {
        return [[collect(Helper::ERROR_STATUS_CODE_INFO)->max(static fn(array $i) => max(array_keys($i))) + 1]];
    }

    #[DataProvider('provideXmlResponse')]
    public function testXmlResponse(string|\Stringable $xml): void
    {
        $response = Helper::xmlResponse($xml);
        self::assertEquals('<?xml version="1.0" encoding="UTF-8"?>' . "\n$xml", $response->getContent());
        self::assertEquals('text/xml', $response->headers->get('Content-Type'));
    }

    public static function provideXmlResponse(): array
    {
        return [['<test />'], [new UnicodeString('<test />')]];
    }
}
