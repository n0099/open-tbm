<?php

namespace App\Exceptions;

/**
 * Class ExceptionAdditionInfo
 *
 * Maintain global exception addition info config which will be report while exception thrown
 *
 * @package App\Exceptions
 */
class ExceptionAdditionInfo
{
    public static $info = [];

    public static function set(array $info): void
    {
        static::$info = $info + static::$info;
    }

    public static function remove(...$infoName): void
    {
        foreach (func_get_args() as $infoName) {
            unset(static::$info[$infoName]);
        }
    }

    public static function format(): string
    {
        return urldecode(http_build_query(static::$info ?? [], null, ', '));
    }
}
