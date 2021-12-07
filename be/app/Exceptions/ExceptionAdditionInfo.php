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
    public static array $info = [];

    public static function set(array $info): void
    {
        self::$info = array_merge(self::$info, $info);
    }

    public static function remove(...$infoNames): void
    {
        foreach ($infoNames as $infoName) {
            unset(self::$info[$infoName]);
        }
    }

    public static function format(): string
    {
        return json_encode(self::$info ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
