<?php

namespace App\Tests\Eloquent;

use App\Eloquent\ModelAttributeMaker;
use Illuminate\Database\Eloquent\Casts\Attribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use TbClient\Wrapper\PostContentWrapper;
use Tests\TestCase;

#[CoversClass(ModelAttributeMaker::class)]
class ModelAttributeMakerTest extends TestCase
{
    public static function resourceAttributeGetter(Attribute $attribute, string $fileContent, $expected): void
    {
        $getter = $attribute->get;
        self::assertNull($getter(null));

        $fileStream = \Safe\tmpfile();
        \Safe\fwrite($fileStream, $fileContent);
        \Safe\rewind($fileStream);
        self::assertEquals($expected, $getter($fileStream));
    }

    #[DataProvider('provideMakeProtoBufAttribute')]
    public function testMakeProtoBufAttribute(string $jsonString): void
    {
        $attribute = ModelAttributeMaker::makeProtoBufAttribute(PostContentWrapper::class);
        self::assertTrue($attribute->withCaching);
        $contentProtoBuf = new PostContentWrapper();
        $contentProtoBuf->mergeFromJsonString($jsonString);
        self::resourceAttributeGetter(
            $attribute,
            $contentProtoBuf->serializeToString(),
            \Safe\json_decode($jsonString),
        );
    }

    public static function provideMakeProtoBufAttribute(): array
    {
        return [['{ "value": [{ "text": "test" }] }']];
    }

    #[DataProvider('provideMakeResourceAttribute')]
    public function testMakeResourceAttribute(string $data): void
    {
        $attribute = ModelAttributeMaker::makeResourceAttribute();
        self::assertTrue($attribute->withCaching);
        self::resourceAttributeGetter($attribute, $data, $data);
    }

    public static function provideMakeResourceAttribute(): array
    {
        return [['test']];
    }
}
