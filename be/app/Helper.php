<?php

namespace App;

use Illuminate\Support\Arr;
use JetBrains\PhpStorm\Pure;
use GuzzleHttp\Utils;

class Helper
{
    public const POSTS_ID = ['tid', 'pid', 'spid'];

    public const POST_TYPES = ['thread', 'reply', 'subReply'];

    public const POST_TYPES_PLURAL = ['threads', 'replies', 'subReplies'];

    public const POSTS_ID_TYPE = [
        'tid' => 'thread',
        'pid' => 'reply',
        'spid' => 'subReply'
    ];

    public const POSTS_TYPE_ID = [
        'thread' => 'tid',
        'reply' => 'pid',
        'subReply' => 'spid'
    ];

    public static function abortAPIIf(int $errorCode, bool $condition): void
    {
        if ($condition) {
            self::abortAPI($errorCode);
        }
    }

    public static function abortAPIIfNot(int $errorCode, bool $condition): void
    {
        if (!$condition) {
            self::abortAPI($errorCode);
        }
    }

    public static function abortAPI(int $errorCode): void
    {
        $statusCodeAndErrorInfos = [
            // httpStatusCode => [ errorCode => errorInfo ]
            400 => [
                // 40000 => App\Exceptions\Handler::convertValidationExceptionToResponse()
                40001 => '贴子查询类型必须为索引或搜索查询',
                40002 => '搜索查询必须指定查询贴吧',
                40003 => '部分查询参数与查询贴子类型要求不匹配',
                40004 => '排序方式与查询贴子类型要求不匹配',
                40005 => '请只提供一个唯一查询参数'
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

    public static function keyBy(array $array, string $itemsKey): array
    {
        // similar with Illuminate\Support\Collection::keyBy()
        // https://stackoverflow.com/questions/56108051/is-it-possible-to-assign-keys-to-array-elements-in-php-from-a-value-column-with
        // note array_column won't check is every item have determined key, if not it will fill with numeric key
        return array_column($array, null, $itemsKey);
    }

    public static function nullableValidate($value, bool $isJson = false)
    {
        if ($value === '""' || $value === '[]' || blank($value)) {
            return null;
        }
        return $isJson ? Utils::jsonEncode($value) : $value;
    }

    public static function isArrayValuesAllEqualTo(array $haystack, $equalTo): bool
    {
        return array_filter($haystack, static fn ($value) => $value !== $equalTo) === [];
    }

    #[Pure] public static function rawSqlGroupByTimeGranularity(
        string $fieldName,
        array $timeGranularity = ['minute', 'hour', 'day', 'week', 'month', 'year']
    ): array {
        return Arr::only([
            'minute' => "DATE_FORMAT({$fieldName}, \"%Y-%m-%d %H:%i\") AS time",
            'hour' => "DATE_FORMAT({$fieldName}, \"%Y-%m-%d %H:00\") AS time",
            'day' => "DATE({$fieldName}) AS time",
            'week' => "DATE_FORMAT({$fieldName}, \"%Y年第%u周\") AS time",
            'month' => "DATE_FORMAT({$fieldName}, \"%Y-%m\") AS time",
            'year' => "DATE_FORMAT({$fieldName}, \"%Y年\") AS time"
        ], $timeGranularity);
    }

    public static function timestampToLocalDateTime(int $timestamp): string
    {
        return date("Y-m-d\TH:i:s", $timestamp);
    }
}
