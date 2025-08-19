<?php

namespace App\Tests\Repository\Post;

use App\Repository\Post\PostRepositoryFactory;
use App\Repository\RepositoryWithSplitFid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(PostRepositoryFactory::class)]
#[CoversClass(RepositoryWithSplitFid::class)]
class PostRepositoryFactoryTest extends KernelTestCase
{
    private PostRepositoryFactory $sut;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->sut = static::getContainer()->get(PostRepositoryFactory::class);
    }

    #[DataProvider('providePostModelFid')]
    public function testPostModelFid(int $fid): void
    {
        self::assertEquals($fid, $this->sut->newThread($fid)->fid);
        self::assertEquals($fid, $this->sut->newReply($fid)->fid);
        self::assertEquals($fid, $this->sut->newReplyContent($fid)->fid);
        self::assertEquals($fid, $this->sut->newSubReply($fid)->fid);
        self::assertEquals($fid, $this->sut->newSubReplyContent($fid)->fid);
        self::assertEquals($fid, $this->sut->newForumPosts($fid)['thread']->fid);
        self::assertEquals($fid, $this->sut->newForumPosts($fid)['reply']->fid);
        self::assertEquals($fid, $this->sut->newForumPosts($fid)['subReply']->fid);
        self::assertEquals($fid, $this->sut->new($fid, 'thread')->fid);
        self::assertEquals($fid, $this->sut->new($fid, 'reply')->fid);
        self::assertEquals($fid, $this->sut->new($fid, 'subReply')->fid);
    }

    public static function providePostModelFid(): array
    {
        return [[0]];
    }
}
