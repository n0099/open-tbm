<?php

namespace Tests\Feature\App\Eloquent\Model\Post;

use App\Eloquent\Model\Post\Post;
use App\Eloquent\Model\Post\PostFactory;
use App\Eloquent\ModelWithTableNameSplitByFid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\TestCase;

#[CoversClass(PostFactory::class)]
#[CoversClass(ModelWithTableNameSplitByFid::class)]
#[CoversClass(Post::class)]
class PostFactoryTest extends TestCase
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
