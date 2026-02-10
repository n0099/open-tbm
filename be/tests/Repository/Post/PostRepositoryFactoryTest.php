<?php

namespace App\Tests\Repository\Post;

use App\Repository\Post\PostRepositoryFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(PostRepositoryFactory::class)]
class PostRepositoryFactoryTest extends KernelTestCase
{
    private PostRepositoryFactory $sut;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->sut = static::getContainer()->get(PostRepositoryFactory::class);
    }

    public function test(): void
    {
        self::assertEquals($this->sut->newForumPosts()['thread'], $this->sut->new('thread'));
        self::assertEquals($this->sut->newForumPosts()['reply'], $this->sut->new('reply'));
        self::assertEquals($this->sut->newForumPosts()['subReply'], $this->sut->new('subReply'));
    }
}
