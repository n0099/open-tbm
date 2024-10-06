<?php

namespace App;

use Symfony\Component\HttpKernel\Exception\HttpException;

class Helper
{
    public const array POST_ID = ['tid', 'pid', 'spid'];

    public const array POST_TYPES = ['thread', 'reply', 'subReply'];

    public const array POST_TYPES_PLURAL = ['threads', 'replies', 'subReplies'];

    public const array POST_TYPE_TO_PLURAL = [
        'thread' => 'threads',
        'reply' => 'replies',
        'subReply' => 'subReplies',
    ];

    public const array POST_TYPE_PLURAL_TO_SINGULAR = [
        'threads' => 'thread',
        'replies' => 'reply',
        'subReplies' => 'subReply',
    ];

    public const array POST_ID_TO_TYPE_PLURAL = [
        'tid' => 'threads',
        'pid' => 'replies',
        'spid' => 'subReplies',
    ];

    public const array POST_ID_TO_TYPE = [
        'tid' => 'thread',
        'pid' => 'reply',
        'spid' => 'subReply',
    ];

    public const array POST_TYPE_TO_ID = [
        'thread' => 'tid',
        'reply' => 'pid',
        'subReply' => 'spid',
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

    public const array ERROR_STATUS_CODE_INFO = [
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
        ],
    ];

    public static function abortAPI(int $errorCode): never
    {
        $statusCode = 0;
        $errorInfo = null;
        foreach (self::ERROR_STATUS_CODE_INFO as $infoStatusCode => $infoErrorInfo) {
            if (\array_key_exists($errorCode, $infoErrorInfo)) {
                $statusCode = $infoStatusCode;
                $errorInfo = $infoErrorInfo[$errorCode];
            }
        }
        if ($errorInfo === null) {
            throw new \InvalidArgumentException('Given error code doesn\'t existed');
        }
        $json = \Safe\json_encode(compact('errorCode', 'errorInfo'));
        throw HttpException::fromStatusCode($statusCode, $json, code: $errorCode);
    }
}
