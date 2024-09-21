<?php

namespace App\Http\PostsQuery;

use App\Eloquent\Model\Post\Post;
use App\Eloquent\Model\Post\Reply;
use App\Eloquent\Model\Post\SubReply;
use App\Eloquent\Model\Post\Thread;
use Illuminate\Pagination\Cursor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    /** @backupStaticAttributes enabled */
    public function encodeNextCursor(): void
    {
        (new \ReflectionClass(Post::class))->setStaticPropertyValue('unguarded', true);
        $input = collect([
            'threads' => [new Thread(['tid' => 1, 'postedAt' => 0])],
            'replies' => [new Reply(['pid' => 2, 'postedAt' => -2147483649])],
            'subReplies' => [new SubReply(['spid' => 3, 'postedAt' => 'test'])],
        ])->recursive(maxDepth: 0);
        self::assertEquals('AQ,0,Ag,-:____fw,Aw,S:test', $this->sut->encodeNextCursor($input, 'postedAt'));
    }

    #[Test]
    public function decodeCursor(): void
    {
        $expected = collect([
            'thread' => new Cursor(['tid' => 1, 'postedAt' => 0]),
            'reply' => new Cursor(['pid' => 2, 'postedAt' => -2147483649]),
            'subReply' => new Cursor(['spid' => 3, 'postedAt' => 'test']),
        ]);
        self::assertEquals($expected, $this->sut->decodeCursor('AQ,0,Ag,-:____fw,Aw,S:test', 'postedAt'));

        $expected = collect([
            'thread' => new Cursor(['tid' => 0, 'postedAt' => 0]),
            'reply' => new Cursor(['pid' => 0, 'postedAt' => 0]),
            'subReply' => new Cursor(['spid' => 0, 'postedAt' => 0]),
        ]);
        self::assertEquals($expected, $this->sut->decodeCursor(',,,,0,0', 'postedAt'));
    }
}
