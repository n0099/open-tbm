<?php

namespace App\Repository\Post;

use App\Helper;
use App\Repository\Post\Content\ReplyContentRepository;
use App\Repository\Post\Content\SubReplyContentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

readonly class PostRepositoryFactory
{
    public function __construct(
        private ManagerRegistry $registry,
        private EntityManagerInterface $entityManager,
    ) {}

    public function newThread(int $fid): ThreadRepository
    {
        return new ThreadRepository($this->registry, $this->entityManager, $fid, $this);
    }

    public function newReply(int $fid): ReplyRepository
    {
        return new ReplyRepository($this->registry, $this->entityManager, $fid);
    }

    public function newReplyContent(int $fid): ReplyContentRepository
    {
        return new ReplyContentRepository($this->registry, $this->entityManager, $fid);
    }

    public function newSubReply(int $fid): SubReplyRepository
    {
        return new SubReplyRepository($this->registry, $this->entityManager, $fid);
    }

    public function newSubReplyContent(int $fid): SubReplyContentRepository
    {
        return new SubReplyContentRepository($this->registry, $this->entityManager, $fid);
    }

    /** @return array{thread: ThreadRepository, reply: ReplyRepository, subReply: SubReplyRepository} */
    public function newForumPosts(int $fid): array
    {
        return array_combine(
            Helper::POST_TYPES,
            [$this->newThread($fid), $this->newReply($fid), $this->newSubReply($fid)],
        );
    }

    public function new(int $fid, string $postType): PostRepository
    {
        return match ($postType) {
            'thread' => $this->newThread($fid),
            'reply' => $this->newReply($fid),
            'subReply' => $this->newSubReply($fid),
        };
    }
}
