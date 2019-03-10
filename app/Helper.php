<?php

namespace App;

use function GuzzleHttp\json_encode;

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

    public static function nullableValidate($value, bool $isJson = false)
    {
        if ($value === '""' || $value === '[]' || blank($value)) {
            return null;
        }

        return $isJson ? json_encode($value) : $value;
    }
}
