<?php

namespace App\Tests;

use App\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[CoversClass(Utils::class)]
class UtilsTest extends TestCase
{
    public static function assertAbort(int $statusCode, int $errorCode, string $errorInfo, callable $callback): void
    {
        try {
            $callback();
        } catch (HttpException $e) {
            self::assertEquals($statusCode, $e->getStatusCode());
            $json = \Safe\json_decode($e->getMessage());
            self::assertEquals($errorCode, $json->errorCode);
            self::assertEquals($errorInfo, $json->errorInfo);
        }
    }

    #[DataProvider('provideAbortAPI')]
    public function testAbortAPI(int $statusCode, int $errorCode, string $errorInfo): void
    {
        self::assertAbort($statusCode, $errorCode, $errorInfo, static fn() => Utils::abortAPI($errorCode));
    }

    #[DataProvider('provideAbortAPI')]
    public function testAbortAPIIf(int $statusCode, int $errorCode, string $errorInfo): void
    {
        self::assertAbort($statusCode, $errorCode, $errorInfo, static fn() => Utils::abortAPIIf($errorCode, true));
    }

    #[DataProvider('provideAbortAPI')]
    public function testAbortAPIIfNot(int $statusCode, int $errorCode, string $errorInfo): void
    {
        self::assertAbort($statusCode, $errorCode, $errorInfo, static fn() => Utils::abortAPIIfNot($errorCode, false));
    }

    public static function provideAbortAPI(): array
    {
        return collect(Utils::ERROR_STATUS_CODE_INFO)
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
        Utils::abortAPI($errorCode);
    }

    public static function provideAbortAPIWithNonExistsCode(): array
    {
        return [[collect(Utils::ERROR_STATUS_CODE_INFO)->max(static fn(array $i) => max(array_keys($i))) + 1]];
    }
}
