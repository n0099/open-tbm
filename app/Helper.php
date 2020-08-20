<?php

namespace App;

use Illuminate\Support\Arr;

class Helper
{
    public static function abortAPIIf(int $errorCode, bool $condition): void
    {
        if ($condition) {
            static::abortAPI($errorCode);
        }
    }

    public static function abortAPIIfNot(int $errorCode, bool $condition): void
    {
        if (! $condition) {
            static::abortAPI($errorCode);
        }
    }

    public static function abortAPI(int $errorCode): void
    {
        $statusCodeAndErrorInfos = [
            // httpStatusCode => [ errorCode => errorInfo ]
            400 => [
                // 40000 => App\Exceptions\Handler->convertValidationExceptionToResponse()
                40001 => '贴子查询类型必须为索引或搜索查询',
                40002 => '搜索查询必须指定查询贴吧',
                40003 => '最后回复人用户参数仅支持主题贴查询',
                40004 => '部分贴子用户信息查询参数不支持用于主题贴最后回复人',
                40005 => '部分查询参数与查询贴子类型要求不匹配',
                40006 => '排序方式与查询贴子类型要求不匹配'
            ],
            401 => [
                40101 => 'Google reCAPTCHA 验证未通过 请刷新页面/更换设备/网络环境后重试'
            ],
            404 => [
                40401 => '贴子查询结果为空',
                40402 => '用户查询结果为空',
                40403 => '吧贴量统计查询结果为空'
            ]
        ];

        $statusCode = 0;
        $errorInfo = null;
        foreach ($statusCodeAndErrorInfos as $infoStatusCode => $infoErrorInfo) {
            if (\array_key_exists($errorCode, $infoErrorInfo)) {
                $statusCode = $infoStatusCode;
                $errorInfo = $infoErrorInfo[$errorCode];
            }
        }
        if ($errorInfo === null) {
            throw new \InvalidArgumentException('given error code doesn\'t existed');
        }
        \Response::json([
            'errorCode' => $errorCode,
            'errorInfo' => $errorInfo
        ], $statusCode)->send();
        exit;
    }

    public static function setKeyWithItemsValue(array $array, string $itemsKey): array
    {
        $return = [];
        foreach ($array as $item) {
            $return[$item[$itemsKey]] = $item;
        }
        return $return;
    }

    public static function getArrayValuesByKeys(array $haystack, array $keys): array
    {
        $return = [];
        foreach ($keys as $key) {
            $return[$key] = $haystack[$key];
        }
        return $return;
    }

    public static function nullableValidate($value, bool $isJson = false)
    {
        if ($value === '""' || $value === '[]' || blank($value)) {
            return null;
        }
        return $isJson ? json_encode($value, JSON_THROW_ON_ERROR) : $value;
    }

    public static function isArrayValuesAllEqualTo(array $haystack, $equalTo): bool
    {
        return array_filter($haystack, fn($value) => $value !== $equalTo) === [];
    }

    public static function getRawSqlGroupByTimeRange(string $fieldName, array $timeRanges = ['minute', 'hour', 'day', 'week', 'month', 'year']): array
    {
        return Arr::only([
            'minute' => "DATE_FORMAT({$fieldName}, \"%Y-%m-%d %H:%i\") AS time",
            'hour' => "DATE_FORMAT({$fieldName}, \"%Y-%m-%d %H:00\") AS time",
            'day' => "DATE({$fieldName}) AS time",
            'week' => "DATE_FORMAT({$fieldName}, \"%Y第%u周\") AS time",
            'month' => "DATE_FORMAT({$fieldName}, \"%Y-%m\") AS time",
            'year' => "DATE_FORMAT({$fieldName}, \"%Y年\") AS time"
        ], $timeRanges);
    }
}
