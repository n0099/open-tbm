<?php

namespace App\Tests\Repository\Post;

use App\Entity\Post\Post;
use App\Repository\Post\PostRepositoryFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(PostRepositoryFactory::class)]
#[CoversClass(Post::class)]
class PostRepositoryFactoryTest extends KernelTestCase
{
    #[DataProvider('providePostModelFid')]
    public function testPostModelFid(int $fid): void
    {
        $prop = new ReflectionProperty(ModelWithTableNameSplitByFid::class, 'fid');
        self::assertEquals($fid, $prop->getValue(PostFactory::newThread($fid)));
        self::assertEquals($fid, $prop->getValue(PostFactory::newReply($fid)));
        self::assertEquals($fid, $prop->getValue(PostFactory::newReplyContent($fid)));
        self::assertEquals($fid, $prop->getValue(PostFactory::newSubReply($fid)));
        self::assertEquals($fid, $prop->getValue(PostFactory::newSubReplyContent($fid)));
        self::assertEquals($fid, $prop->getValue(PostFactory::getPostModelsByFid($fid)['thread']));
        self::assertEquals($fid, $prop->getValue(PostFactory::getPostModelsByFid($fid)['reply']));
        self::assertEquals($fid, $prop->getValue(PostFactory::getPostModelsByFid($fid)['subReply']));
    }

    public static function providePostModelFid(): array
    {
        return [[0]];
    }
}
