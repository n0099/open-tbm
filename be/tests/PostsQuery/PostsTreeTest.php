<?php

namespace App\Tests\PostsQuery;

use App\DTO\Post\Reply;
use App\DTO\Post\SubReply;
use App\DTO\Post\Thread;
use App\PostsQuery\PostsTree;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(PostsTree::class)]
class PostsTreeTest extends KernelTestCase
{
    private PostsTree $sut;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->sut = self::getContainer()->get(PostsTree::class);
    }

    #[DataProvider('provideReOrderNestedPostsData')]
    public function testReOrderNestedPosts(Collection $input, bool $isOrderByDesc, Collection $expected): void
    {
        self::assertEquals($expected, $this->sut->reOrderNestedPosts($input, 'postedAt', $isOrderByDesc));
    }

    public static function provideReOrderNestedPostsData(): array
    {
        $input = collect([
            tap(new Thread(), static function (Thread $value) {
                $value->postedAt = 1;
                $value->isMatchQuery = true;
                $value->replies = collect([
                    tap(new Reply(), static function (Reply $value) {
                        $value->postedAt = 2;
                        $value->isMatchQuery = true;
                        $value->subReplies = collect([
                            tap(new SubReply(), static fn(SubReply $value)
                                => $value->postedAt = 30),
                        ]);
                    }),
                    tap(new Reply(), static function (Reply $value) {
                        $value->postedAt = 20;
                        $value->isMatchQuery = false;
                        $value->subReplies = collect([
                            tap(new SubReply(), static fn(SubReply $value)
                                => $value->postedAt = 3),
                        ]);
                    }),
                    tap(new Reply(), static function (Reply $value) {
                        $value->postedAt = 4;
                        $value->isMatchQuery = false;
                        $value->subReplies = collect([
                            tap(new SubReply(), static fn(SubReply $value)
                                => $value->postedAt = 5),
                            tap(new SubReply(), static function (SubReply $value) {
                                $value->postedAt = 33;
                                $value->isMatchQuery = false;
                            }),
                            tap(new SubReply(), static fn(SubReply $value)
                                => $value->postedAt = 60),
                        ]);
                    }),
                ]);
            }),
            tap(new Thread(), static function (Thread $value) {
                $value->postedAt = 7;
                $value->isMatchQuery = false;
                $value->replies = collect([
                    tap(new Reply(), static function (Reply $value) {
                        $value->postedAt = 31;
                        $value->isMatchQuery = true;
                        $value->subReplies = collect();
                    }),
                ]);
            }),
        ]);
        $expectedWhenOrderByAsc = collect([
            tap(new Thread(), static function (Thread $value) {
                $value->postedAt = 1;
                $value->isMatchQuery = true;
                $value->replies = collect([
                    tap(new Reply(), static function (Reply $value) {
                        $value->postedAt = 2;
                        $value->isMatchQuery = true;
                        $value->subReplies = collect([
                            tap(new SubReply(), static fn(SubReply $value)
                            => $value->postedAt = 30),
                        ]);
                        $value->sortingKey = 2;
                    }),
                    tap(new Reply(), static function (Reply $value) {
                        $value->postedAt = 20;
                        $value->isMatchQuery = false;
                        $value->subReplies = collect([
                            tap(new SubReply(), static fn(SubReply $value)
                            => $value->postedAt = 3),
                        ]);
                        $value->sortingKey = 3;
                    }),
                    tap(new Reply(), static function (Reply $value) {
                        $value->postedAt = 4;
                        $value->isMatchQuery = false;
                        $value->subReplies = collect([
                            tap(new SubReply(), static fn(SubReply $value)
                                => $value->postedAt = 5),
                            tap(new SubReply(), static function (SubReply $value) {
                                $value->postedAt = 33;
                                $value->isMatchQuery = false;
                            }),
                            tap(new SubReply(), static fn(SubReply $value)
                                => $value->postedAt = 60),
                        ]);
                        $value->sortingKey = 5;
                    }),
                ]);
                $value->sortingKey = 1;
            }),
            tap(new Thread(), static function (Thread $value) {
                $value->postedAt = 7;
                $value->isMatchQuery = false;
                $value->replies = collect([
                    tap(new Reply(), static function (Reply $value) {
                        $value->postedAt = 31;
                        $value->isMatchQuery = true;
                        $value->subReplies = collect();
                        $value->sortingKey = 31;
                    }),
                ]);
                $value->sortingKey = 31;
            }),
        ]);
        $expectedWhenOrderByDesc = collect([
            tap(new Thread(), static function (Thread $value) {
                $value->postedAt = 1;
                $value->isMatchQuery = true;
                $value->replies = collect([
                    tap(new Reply(), static function (Reply $value) {
                        $value->postedAt = 4;
                        $value->isMatchQuery = false;
                        $value->subReplies = collect([
                            tap(new SubReply(), static fn(SubReply $value)
                                => $value->postedAt = 60),
                            tap(new SubReply(), static function (SubReply $value) {
                                $value->postedAt = 33;
                                $value->isMatchQuery = false;
                            }),
                            tap(new SubReply(), static fn(SubReply $value)
                                => $value->postedAt = 5),
                        ]);
                        $value->sortingKey = 60;
                    }),
                    tap(new Reply(), static function (Reply $value) {
                        $value->postedAt = 2;
                        $value->isMatchQuery = true;
                        $value->subReplies = collect([
                            tap(new SubReply(), static fn(SubReply $value)
                            => $value->postedAt = 30),
                        ]);
                        $value->sortingKey = 30;
                    }),
                    tap(new Reply(), static function (Reply $value) {
                        $value->postedAt = 20;
                        $value->isMatchQuery = false;
                        $value->subReplies = collect([
                            tap(new SubReply(), static fn(SubReply $value)
                            => $value->postedAt = 3),
                        ]);
                        $value->sortingKey = 3;
                    }),
                ]);
                $value->sortingKey = 60;
            }),
            tap(new Thread(), static function (Thread $value) {
                $value->postedAt = 7;
                $value->isMatchQuery = false;
                $value->replies = collect([
                    tap(new Reply(), static function (Reply $value) {
                        $value->postedAt = 31;
                        $value->isMatchQuery = true;
                        $value->subReplies = collect();
                        $value->sortingKey = 31;
                    }),
                ]);
                $value->sortingKey = 31;
            }),
        ]);
        return [
            [$input, false, $expectedWhenOrderByAsc],
            [$input, true, $expectedWhenOrderByDesc],
        ];
    }

    /** @param array{threads: Collection<Thread>, replies: Collection<Reply>, subReplies: Collection<SubReply>} $input */
    #[DataProvider('provideNestPostsWithParent')]
    public function testNestPostsWithParent(array $input, Collection $expected): void
    {
        new \ReflectionProperty(PostsTree::class, 'threads')->setValue($this->sut, $input['threads']);
        new \ReflectionProperty(PostsTree::class, 'replies')->setValue($this->sut, $input['replies']);
        new \ReflectionProperty(PostsTree::class, 'subReplies')->setValue($this->sut, $input['subReplies']);
        self::assertEquals($expected, $this->sut->nestPostsWithParent());
    }

    public static function provideNestPostsWithParent(): array
    {
        $thread = new Thread();
        $thread->tid = 1;
        $reply = new Reply();
        $reply->tid = 1;
        $reply->pid = 2;
        $subReply = new SubReply();
        $subReply->tid = 1;
        $subReply->pid = 2;
        $subReply->spid = 3;
        return [[
            [
                'threads' => collect([$thread]),
                'replies' => collect([$reply]),
                'subReplies' => collect([$subReply]),
            ],
            collect([
                tap(clone($thread), static fn(Thread $value) => $value->replies = collect([
                    tap(clone($reply), static fn(Reply $value) => $value->subReplies = collect([$subReply])),
                ])),
            ]),
        ]];
    }
}
