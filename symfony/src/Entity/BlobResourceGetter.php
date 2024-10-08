<?php

namespace App\Entity;

use Google\Protobuf\Internal\Message;

class BlobResourceGetter
{
    /**
     * @param ?resource $value
     * @param class-string<T> $protoBufClass
     * @template T of Message
     */
    public static function protoBuf($value, string $protoBufClass): ?array
    {
        if ($value === null) {
            return null;
        }
        $protoBuf = new $protoBufClass();
        $protoBuf->mergeFromString(self::resource($value));
        // Message->serializeToJsonString() will remove fields with default value
        return \Safe\json_decode($protoBuf->serializeToJsonString(), assoc: true);
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
