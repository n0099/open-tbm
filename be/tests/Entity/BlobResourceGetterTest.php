<?php

namespace App\Tests\Entity;

use App\Entity\BlobResourceGetter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TbClient\Wrapper\PostContentWrapper;

#[CoversClass(BlobResourceGetter::class)]
class BlobResourceGetterTest extends TestCase
{
    /** @return resource */
    public static function makeStreamResource(string $data)
    {
        $stream = \Safe\tmpfile();
        \Safe\fwrite($stream, $data);
        \Safe\rewind($stream);
        return $stream;
    }

    #[DataProvider('provideProtoBuf')]
    public function testProtoBuf(string $jsonString): void
    {
        $protoBufClass = PostContentWrapper::class;
        self::assertNull(BlobResourceGetter::protoBuf(null, $protoBufClass));
        $contentProtoBuf = new PostContentWrapper();
        $contentProtoBuf->mergeFromJsonString($jsonString);
        $resource = self::makeStreamResource($contentProtoBuf->serializeToString());
        self::assertEquals(
            \Safe\json_decode($jsonString, assoc: true),
            BlobResourceGetter::protoBuf($resource, $protoBufClass),
        );
    }

    public static function provideProtoBuf(): array
    {
        return [['{ "value": [{ "text": "test" }] }']];
    }

    #[DataProvider('provideResource')]
    public function testResource(string $data): void
    {
        self::assertNull(BlobResourceGetter::resource(null));
        self::assertEquals($data, BlobResourceGetter::resource(self::makeStreamResource($data)));
    }

    public static function provideResource(): array
    {
        return [['test']];
    }
}
