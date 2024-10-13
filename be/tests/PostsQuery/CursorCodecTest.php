<?php

namespace App\Tests\PostsQuery;

use App\PostsQuery\CursorCodec;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CursorCodec::class)]
class CursorCodecTest extends TestCase
{
    private CursorCodec $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new CursorCodec();
    }

    #[DataProvider('provideEncodeNextCursor')]
    public function testEncodeNextCursor(string $cursor, Collection $input): void
    {
        self::assertEquals($cursor, $this->sut->encodeNextCursor($input));
    }

    public static function provideEncodeNextCursor(): array
    {
        return [[
            'AQ,0,Ag,-:____fw,Aw,S:test',
            collect([
                'threads' => collect([['tid' => 1, 'postedAt' => 0]]),
                'replies' => collect([['pid' => 2, 'postedAt' => -2147483649]]),
                'subReplies' => collect([['spid' => 3, 'postedAt' => 'test']]),
            ]),
        ]];
    }

    #[DataProvider('provideDecodeCursor')]
    public function testDecodeCursor(string $cursor, array $expected): void
    {
        self::assertEquals(collect($expected), $this->sut->decodeCursor($cursor, 'postedAt'));
    }

    public static function provideDecodeCursor(): array
    {
        return [[
            'AQ,0,Ag,-:____fw,Aw,S:test',
            [
                'thread' => ['tid' => 1, 'postedAt' => 0],
                'reply' => ['pid' => 2, 'postedAt' => -2147483649],
                'subReply' => ['spid' => 3, 'postedAt' => 'test'],
            ],
        ], [
            ',,,,0,0',
            [],
        ]];
    }
}
