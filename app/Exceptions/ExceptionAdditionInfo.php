<?php

namespace App\Exceptions;

use function GuzzleHttp\json_encode;

/**
 * Class ExceptionAdditionInfo
 *
 * Maintain global exception addition info config which will be report while exception thrown
 *
 * @package App\Exceptions
 */
class ExceptionAdditionInfo
{
    public static array $info = [];

    public static function set(array $info): void
    {
        static::$info = $info + static::$info;
    }

    public static function remove(...$infoNames): void
    {
        foreach ($infoNames as $infoName) {
            unset(static::$info[$infoName]);
        }
    }

    public static function format(): string
    {
        return json_encode(static::$info ?? [], JSON_UNESCAPED_UNICODE);
    }
}
