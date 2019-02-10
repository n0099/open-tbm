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
    public static function set(array $info): void
    {
        config([
            'globalExceptionAdditionInfo' => config('globalExceptionAdditionInfo') ?? [] + $info
        ]);
    }

    public static function remove(...$infoName): void
    {
        $vanillaAdditionInfo = config('globalExceptionAdditionInfo');
        foreach (func_get_args() as $infoName) {
            unset($vanillaAdditionInfo[$infoName]);
        }
        config(['globalExceptionAdditionInfo' => $vanillaAdditionInfo]);
    }
}
