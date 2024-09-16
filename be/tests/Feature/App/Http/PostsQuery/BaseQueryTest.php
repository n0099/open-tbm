<?php

namespace Tests\Feature\App\Http\PostsQuery;

use App\Http\PostsQuery\BaseQuery;
use Barryvdh\Debugbar\LaravelDebugbar;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaseQueryTest extends TestCase
{
    #[Test]
    public function reOrderNestedPosts(): void
    {
        $input = collect([
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
        ])->recursive();
        $output = [
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

        $baseQuery = $this->getMockBuilder(BaseQuery::class)
            ->setConstructorArgs([app(LaravelDebugbar::class)])
            ->getMockForAbstractClass();
        $orderByDesc = new \ReflectionProperty(BaseQuery::class, 'orderByDesc');
        $orderByDesc->setValue($baseQuery, true);
        $orderByField = new \ReflectionProperty(BaseQuery::class, 'orderByField');
        $orderByField->setValue($baseQuery, 'postedAt');
        self::assertEquals($output, $baseQuery->reOrderNestedPosts($input, false));
    }
}
