<?php

namespace App\Tests\PostsQuery;

use App\Entity\Post\Reply;
use App\Entity\Post\SubReply;
use App\Entity\Post\Thread;
use App\PostsQuery\BaseQuery;
use App\PostsQuery\IndexQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(BaseQuery::class)]
class BaseQueryTest extends KernelTestCase
{
    private BaseQuery $sut;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $container = self::getContainer();
        $this->sut = $container->get(IndexQuery::class);
        (new \ReflectionProperty(BaseQuery::class, 'orderByField'))->setValue($this->sut, 'postedAt');
    }

    public function testPerPageItemsDefaultValue(): void
    {
        $prop = new \ReflectionProperty(BaseQuery::class, 'perPageItems');
        self::assertEquals(50, $prop->getValue($this->sut));
    }

    #[DataProvider('provideReOrderNestedPostsData')]
    public function testReOrderNestedPosts(
        array $input,
        bool $orderByDesc,
        array $expected,
        bool $shouldRemoveSortingKey,
    ): void {
        $input = collect($input)->recursive();
        (new \ReflectionProperty(BaseQuery::class, 'orderByDesc'))
            ->setValue($this->sut, $orderByDesc);
        if ($shouldRemoveSortingKey) { // make https://infection.github.io/guide/mutators.html#TrueValue happy
            self::assertEquals($expected, $this->sut->reOrderNestedPosts($input));
        } else {
            self::assertEquals($expected, $this->sut->reOrderNestedPosts($input, shouldRemoveSortingKey: false));
        }
    }

    public static function provideReOrderNestedPostsData(): array
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
                        'subReplies' => [
                            ['postedAt' => 5],
                            ['postedAt' => 33, 'isMatchQuery' => false],
                            ['postedAt' => 60],
                        ],
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
                        'subReplies' => [
                            ['postedAt' => 5],
                            ['postedAt' => 33, 'isMatchQuery' => false],
                            ['postedAt' => 60],
                        ],
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
                        'subReplies' => [
                            ['postedAt' => 5],
                            ['postedAt' => 33, 'isMatchQuery' => false],
                            ['postedAt' => 60],
                        ],
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
                        'subReplies' => [
                            ['postedAt' => 60],
                            ['postedAt' => 33, 'isMatchQuery' => false],
                            ['postedAt' => 5],
                        ],
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

    #[DataProvider('provideNestPostsWithParent')]
    public function testNestPostsWithParent(array $input, array $expected): void
    {
        self::assertEquals(
            collect($expected)->recursive(),
            $this->sut->nestPostsWithParent(...array_map('collect', $input)),
        );
    }

    public static function provideNestPostsWithParent(): array
    {
        return [[
            [
                'threads' => [new Thread(['tid' => 1])],
                'replies' => [new Reply(['tid' => 1, 'pid' => 2])],
                'subReplies' => [new SubReply(['tid' => 1, 'pid' => 2, 'spid' => 3])],
            ],
            [[
                'tid' => 1,
                'replies' => [[
                    'tid' => 1,
                    'pid' => 2,
                    'subReplies' => [['tid' => 1, 'pid' => 2, 'spid' => 3]],
                ]],
            ]],
        ]];
    }
}
