<?php

namespace Tests\Feature\App\Http\PostsQuery;

use App\Eloquent\Model\Post\Post;
use App\Eloquent\Model\Post\Reply;
use App\Eloquent\Model\Post\SubReply;
use App\Eloquent\Model\Post\Thread;
use App\Http\PostsQuery\CursorCodec;
use Illuminate\Pagination\Cursor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

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
    public function testEncodeNextCursor(string $cursor, array $input): void
    {
        self::assertEquals($cursor, $this->sut->encodeNextCursor(collect($input)->recursive(maxDepth: 0), 'postedAt'));
    }

    public static function provideEncodeNextCursor(): array
    {
        (new \ReflectionClass(Post::class))->setStaticPropertyValue('unguarded', true);
        $ret = [[
            'AQ,0,Ag,-:____fw,Aw,S:test',
            [
                'threads' => [new Thread(['tid' => 1, 'postedAt' => 0])],
                'replies' => [new Reply(['pid' => 2, 'postedAt' => -2147483649])],
                'subReplies' => [new SubReply(['spid' => 3, 'postedAt' => 'test'])],
            ],
        ]];
        // https://github.com/sebastianbergmann/phpunit/issues/5103
        (new \ReflectionClass(Post::class))->setStaticPropertyValue('unguarded', false);
        return $ret;
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
                'thread' => new Cursor(['tid' => 1, 'postedAt' => 0]),
                'reply' => new Cursor(['pid' => 2, 'postedAt' => -2147483649]),
                'subReply' => new Cursor(['spid' => 3, 'postedAt' => 'test']),
            ],
        ], [
            ',,,,0,0',
            [
                'thread' => new Cursor(['tid' => 0, 'postedAt' => 0]),
                'reply' => new Cursor(['pid' => 0, 'postedAt' => 0]),
                'subReply' => new Cursor(['spid' => 0, 'postedAt' => 0]),
            ],
        ]];
    }
}
