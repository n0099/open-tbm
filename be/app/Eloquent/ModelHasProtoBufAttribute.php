<?php

namespace App\Eloquent;

use App\Helper;
use Google\Protobuf\Internal\Message;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait ModelHasProtoBufAttribute
{
    /**
     * @param class-string $protoBufClass
     * @return Attribute<\stdClass, void>
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection ReturnTypeCanBeDeclaredInspection
     * DO NOT add return type to prevent being recognized as an attribute cast
     * https://github.com/laravel/framework/blob/v10.13.5/src/Illuminate/Database/Eloquent/Concerns/HasAttributes.php#L2229
     */
    protected static function makeProtoBufAttribute(string $protoBufClass)
    {
        return Attribute::make(get: static function (?string $value) use ($protoBufClass): ?\stdClass {
            if ($value === null) {
                return null;
            }
            /** @var Message $proto */
            $proto = new $protoBufClass();
            $proto->mergeFromString($value);
            return Helper::jsonDecode($proto->serializeToJsonString(), false);
        });
    }
}
