<?php

namespace App;

use function GuzzleHttp\json_encode;

class Helper
{
    public static function abortApiIf(bool $condidtion, int $errorCode): void
    {
        if ($condidtion) {
            static::abortApi($errorCode);
        }
    }

    public static function abortApi(int $errorCode): void
    {
        $errorInfos = [
            // httpStatusCode => [ errorCode => errorInfo ]
            400 => [
                // 40000 => App\Exceptions\Handler->convertValidationExceptionToResponse()
                40001 => '贴子查询类型必须为索引或自定义搜索查询',
                40002 => '自定义贴子查询必须指定查询贴吧',
                40003 => '最后回复人用户参数仅支持主题贴查询',
                40004 => '部分贴子用户信息查询参数不支持用于主题贴最后回复人',
                40005 => '部分自定义贴子查询参数与查询贴子类型要求不匹配',
            ],
            401 => [
                40101 => 'Google reCAPTCHA 验证未通过 请刷新页面/更换设备/网络环境后重试',
            ],
            404 => [
                40401 => '贴子查询结果为空',
                40402 => '用户查询结果为空',
            ],
        ];

        $statusCode = 0;
        $errorInfo = '';
        foreach ($errorInfos as $infoStatusCode => $infoErrorInfo) {
            if (array_key_exists($errorCode, $infoErrorInfo)) {
                $statusCode = $infoStatusCode;
                $errorInfo = $infoErrorInfo[$errorCode];
            }
        }
        if ($errorInfo == null) {
            throw new \InvalidArgumentException('given error code doesn\'t existed');
        }
        \Response::json([
            'errorCode' => $errorCode,
            'errorInfo' => $errorInfo
        ], $statusCode)->send();
        exit;
    }

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
