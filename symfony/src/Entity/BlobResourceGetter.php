<?php

namespace App\Entity;

use Google\Protobuf\Internal\Message;

class BlobResourceGetter
{
    /**
     * @param ?resource $value
     * @param class-string<T> $protoBufClass
     * @template T extends Message
     * @return ?T
     */
    public static function protoBuf($value, string $protoBufClass): ?Message
    {
        if ($value === null) {
            return null;
        }
        $protoBuf = new $protoBufClass();
        $protoBuf->mergeFromString(self::resource($value));
        return $protoBuf;
    }

    /**
     * @param ?resource $value
     */
    public static function resource($value): ?string
    {
        if ($value === null) {
            return null;
        }
        rewind($value);
        return stream_get_contents($value);
    }
}
