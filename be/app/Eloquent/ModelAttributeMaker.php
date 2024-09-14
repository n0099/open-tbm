<?php

namespace App\Eloquent;

use App\Helper;
use Google\Protobuf\Internal\Message;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ModelAttributeMaker
{
    /**
     * @param class-string $protoBufClass
     * @return Attribute<\stdClass, void>
     */
    public static function makeProtoBufAttribute(string $protoBufClass): Attribute
    {
        return Attribute::make(/**
             * @param resource|null $value
             * @return \stdClass|null
             * @throws \JsonException
             */
            get: static function ($value) use ($protoBufClass): ?\stdClass {
                if ($value === null) {
                    return null;
                }
                /** @var Message $proto */
                $proto = new $protoBufClass();
                $proto->mergeFromString(stream_get_contents($value));
                return Helper::jsonDecode($proto->serializeToJsonString(), false);
            },
        )->shouldCache();
    }

    /**
     * @return Attribute<string, void>
     */
    public static function makeResourceAttribute(): Attribute
    {
        return Attribute::make(/**
             * @param resource|null $value
             * @return string
             */
            get: static fn($value) => $value === null ? null : stream_get_contents($value),
        )->shouldCache();
    }
}
