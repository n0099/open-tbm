<?php

namespace App;

class Helper
{
    public static function convertIDListKey(array $list, string $keyName): array
    {
        $newList = [];

        foreach ($list as $item) {
            $newList[$item[$keyName]] = $item;
        }

        return $newList;
    }

    public static function getArrayValuesByKeys(array $haystack, array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $haystack[$key];
        }
        return $values;
    }
}
