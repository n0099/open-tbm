<?php

namespace App\Repository\Post;

use App\Utils;

readonly class PostRepositoryFactory
{
    public function __construct(
        private ThreadRepository $threadRepository,
        private ReplyRepository $replyRepository,
        private SubReplyRepository $subReplyRepository,
    ) {}

    /** @return array{thread: ThreadRepository, reply: ReplyRepository, subReply: SubReplyRepository} */
    public function newForumPosts(): array
    {
        return array_combine(
            Utils::POST_TYPES,
            [$this->threadRepository, $this->replyRepository, $this->subReplyRepository],
        );
    }

    public function new(string $postType): PostRepository
    {
        return match ($postType) {
            'thread' => $this->threadRepository,
            'reply' => $this->replyRepository,
            'subReply' => $this->subReplyRepository,
        };
    }
}
