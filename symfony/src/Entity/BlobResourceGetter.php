<?php

namespace App\Entity;

use Google\Protobuf\Internal\Message;

class BlobResourceGetter
{
    /**
     * @param resource|null $value
     * @param class-string<Message> $protoBufClass
     */
    public static function protoBuf($value, string $protoBufClass): ?\stdClass
    {
        if ($value === null) {
            return null;
        }
        $protoBuf = new $protoBufClass();
        $protoBuf->mergeFromString(self::resource($value));
        return \Safe\json_decode($protoBuf->serializeToJsonString());
    }

    /**
     * @param resource|null $value
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
