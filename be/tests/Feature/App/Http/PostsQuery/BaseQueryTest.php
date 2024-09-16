<?php

namespace Tests\Feature\App\Http\PostsQuery;

use App\Eloquent\Model\Post\Post;
use App\Eloquent\Model\Post\Reply;
use App\Eloquent\Model\Post\SubReply;
use App\Eloquent\Model\Post\Thread;
use App\Http\PostsQuery\BaseQuery;
use Barryvdh\Debugbar\LaravelDebugbar;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaseQueryTest extends TestCase
{
    private BaseQuery $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getMockBuilder(BaseQuery::class)
            ->setConstructorArgs([app(LaravelDebugbar::class)])
            ->getMockForAbstractClass();
        (new \ReflectionProperty(BaseQuery::class, 'orderByField'))
            ->setValue($this->sut, 'postedAt');
    }

    #[Test]
    #[DataProvider('reOrderNestedPostsDataProvider')]
    /** @backupStaticAttributes enabled */
    public function reOrderNestedPosts(
        array $input,
        bool $orderByDesc,
        array $expected,
        bool $shouldRemoveSortingKey,
    ): void {
        $input = collect($input)->recursive();
        (new \ReflectionProperty(BaseQuery::class, 'orderByDesc'))
            ->setValue($this->sut, $orderByDesc);
        self::assertEquals($expected, $this->sut->reOrderNestedPosts($input, $shouldRemoveSortingKey));
    }

    public static function reOrderNestedPostsDataProvider(): array
    {
        $input = [
            [
                'postedAt' => 1,
                'isMatchQuery' => true,
                'replies' => [
                    [
                        'postedAt' => 2,
                        'isMatchQuery' => true,
                        'subReplies' => [['postedAt' => 30]],
                    ],
                    [
                        'postedAt' => 20,
                        'isMatchQuery' => false,
                        'subReplies' => [['postedAt' => 3]],
                    ],
                    [
                        'postedAt' => 4,
                        'isMatchQuery' => false,
                        'subReplies' => [['postedAt' => 5], ['postedAt' => 60]],
                    ],
                ],
            ],
            [
                'postedAt' => 7,
                'isMatchQuery' => false,
                'replies' => [
                    ['postedAt' => 31, 'isMatchQuery' => true, 'subReplies' => []],
                ],
            ],
        ];
        $expectedWhenOrderByAsc = [
            [
                'postedAt' => 1,
                'isMatchQuery' => true,
                'replies' => [
                    [
                        'postedAt' => 2,
                        'isMatchQuery' => true,
                        'subReplies' => [['postedAt' => 30]],
                        'sortingKey' => 2,
                    ],
                    [
                        'postedAt' => 20,
                        'isMatchQuery' => false,
                        'subReplies' => [['postedAt' => 3]],
                        'sortingKey' => 3,
                    ],
                    [
                        'postedAt' => 4,
                        'isMatchQuery' => false,
                        'subReplies' => [['postedAt' => 5], ['postedAt' => 60]],
                        'sortingKey' => 5,
                    ],
                ],
                'sortingKey' => 1,
            ],
            [
                'postedAt' => 7,
                'isMatchQuery' => false,
                'replies' => [
                    [
                        'postedAt' => 31,
                        'isMatchQuery' => true,
                        'subReplies' => [],
                        'sortingKey' => 31,
                    ],
                ],
                'sortingKey' => 31,
            ],
        ];
        $expectedWhenOrderByAscRemoveSortingKey = [
            [
                'postedAt' => 1,
                'isMatchQuery' => true,
                'replies' => [
                    [
                        'postedAt' => 2,
                        'isMatchQuery' => true,
                        'subReplies' => [['postedAt' => 30]],
                    ],
                    [
                        'postedAt' => 20,
                        'isMatchQuery' => false,
                        'subReplies' => [['postedAt' => 3]],
                    ],
                    [
                        'postedAt' => 4,
                        'isMatchQuery' => false,
                        'subReplies' => [['postedAt' => 5], ['postedAt' => 60]],
                    ],
                ],
            ],
            [
                'postedAt' => 7,
                'isMatchQuery' => false,
                'replies' => [
                    [
                        'postedAt' => 31,
                        'isMatchQuery' => true,
                        'subReplies' => [],
                    ],
                ],
            ],
        ];
        $expectedWhenOrderByDesc = [
            [
                'postedAt' => 1,
                'isMatchQuery' => true,
                'replies' => [
                    [
                        'postedAt' => 4,
                        'isMatchQuery' => false,
                        'subReplies' => [['postedAt' => 60], ['postedAt' => 5]],
                        'sortingKey' => 60,
                    ],
                    [
                        'postedAt' => 2,
                        'isMatchQuery' => true,
                        'subReplies' => [['postedAt' => 30]],
                        'sortingKey' => 30,
                    ],
                    [
                        'postedAt' => 20,
                        'isMatchQuery' => false,
                        'subReplies' => [['postedAt' => 3]],
                        'sortingKey' => 3,
                    ],
                ],
                'sortingKey' => 60,
            ],
            [
                'postedAt' => 7,
                'isMatchQuery' => false,
                'replies' => [
                    [
                        'postedAt' => 31,
                        'isMatchQuery' => true,
                        'subReplies' => [],
                        'sortingKey' => 31,
                    ],
                ],
                'sortingKey' => 31,
            ],
        ];
        return [
            [$input, false, $expectedWhenOrderByAsc, false],
            [$input, true, $expectedWhenOrderByDesc, false],
            [$input, false, $expectedWhenOrderByAscRemoveSortingKey, true],
        ];
    }

    #[Test]
    /** @backupStaticAttributes enabled */
    public function encodeNextCursor(): void
    {
        (new \ReflectionClass(Post::class))->setStaticPropertyValue('unguarded', true);
        $input = collect([
            'threads' => collect([new Thread(['tid' => 1, 'postedAt' => 0])]),
            'replies' => collect([new Reply(['pid' => 2, 'postedAt' => -2147483649])]),
            'subReplies' => collect([new SubReply(['spid' => 3, 'postedAt' => 'test'])]),
        ]);
        self::assertEquals('AQ,0,Ag,-:____fw,Aw,S:test', $this->sut->encodeNextCursor($input));
    }
}
