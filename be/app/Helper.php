<?php

namespace App;

use Illuminate\Support\Arr;
use JetBrains\PhpStorm\Pure;

class Helper
{
    public const POST_ID = ['tid', 'pid', 'spid'];

    public const POST_TYPES = ['thread', 'reply', 'subReply'];

    public const POST_TYPES_PLURAL = ['threads', 'replies', 'subReplies'];

    public const POST_TYPE_TO_PLURAL = [
        'thread' => 'threads',
        'reply' => 'replies',
        'subReply' => 'subReplies'
    ];

    public const POST_TYPE_PLURAL_TO_TYPE = [
        'threads' => 'thread',
        'replies' => 'reply',
        'subReplies' => 'subReply'
    ];

    public const POST_ID_TO_TYPE_PLURAL = [
        'tid' => 'threads',
        'pid' => 'replies',
        'spid' => 'subReplies'
    ];

    public const POST_ID_TO_TYPE = [
        'tid' => 'thread',
        'pid' => 'reply',
        'spid' => 'subReply'
    ];

    public const POST_TYPE_TO_ID = [
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

    public static function abortAPI(int $errorCode): never
    {
        $statusCodeAndErrorInfos = [
            // httpStatusCode => [ errorCode => errorInfo ]
            400 => [
                // 40000 => App\Exceptions\Handler::convertValidationExceptionToResponse()
                40001 => '帖子查询类型必须为索引或搜索查询',
                40002 => '搜索查询必须指定查询贴吧',
                40003 => '部分查询参数与查询帖子类型要求不匹配',
                40004 => '排序方式与查询帖子类型要求不匹配',
                40005 => '提供了多个唯一查询参数',
            ],
            401 => [
                40101 => 'Google reCAPTCHA 验证未通过 请刷新页面/更换设备/网络环境后重试',
            ],
            404 => [
                40401 => '帖子查询结果为空',
                40402 => '用户查询结果为空',
                40403 => '吧帖量统计查询结果为空',
                40406 => '指定查询的贴吧不存在',
            ],
            500 => [
                50001 => '数据库中存在多个贴吧表存储了该 ID 的帖子',
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
            throw new \InvalidArgumentException('Given error code doesn\'t existed');
        }
        response()->json(compact('errorCode', 'errorInfo'), $statusCode)->throwResponse();
        exit;
    }

    /**
     * @throws \JsonException
     */
    public static function nullableValidate(string $value, bool $isJson = false): ?string
    {
        if ($value === '""' || $value === '[]' || blank($value)) {
            return null;
        }
        return $isJson ? self::jsonEncode($value) : $value;
    }

    /**
     * @return string[]
     * @psalm-return array{minute: string, hour: string, day: string, week: string, month: string, year: string}
     */
    #[Pure] public static function rawSqlGroupByTimeGranularity(
        string $fieldName,
        array $timeGranularity = ['minute', 'hour', 'day', 'week', 'month', 'year']
    ): array {
        return Arr::only([
            'minute' => "DATE_FORMAT($fieldName, \"%Y-%m-%d %H:%i\") AS time",
            'hour' => "DATE_FORMAT($fieldName, \"%Y-%m-%d %H:00\") AS time",
            'day' => "DATE($fieldName) AS time",
            'week' => "DATE_FORMAT($fieldName, \"%Y年第%u周\") AS time",
            'month' => "DATE_FORMAT($fieldName, \"%Y-%m\") AS time",
            'year' => "DATE_FORMAT($fieldName, \"%Y年\") AS time"
        ], $timeGranularity);
    }

    public static function timestampToLocalDateTime(int $timestamp): string
    {
        return date("Y-m-d\TH:i:s", $timestamp);
    }

    /**
     * @throws \JsonException
     */
    public static function jsonEncode($value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws \JsonException
     */
    public static function jsonDecode(string $json, bool $assoc = true)
    {
        return json_decode($json, $assoc, flags: JSON_THROW_ON_ERROR);
    }

    public static function xmlResponse(string|\Stringable $xml): \Illuminate\Http\Response
    { // https://laracasts.com/discuss/channels/laravel/syntax-error-unexpected-identifier-version-1
        return response('<?xml version="1.0" encoding="UTF-8"?>' . "\n$xml")
            ->withHeaders(['Content-Type' => 'text/xml']);
    }
}
