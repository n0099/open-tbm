<?php

namespace App\Repository\Post;

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
        return new ThreadRepository($this->registry, $this->entityManager, $fid);
    }

    public function newReply(int $fid): ReplyRepository
    {
        return new ReplyRepository($this->registry, $this->entityManager, $fid);
    }

    public function newSubReply(int $fid): SubReplyRepository
    {
        return new SubReplyRepository($this->registry, $this->entityManager, $fid);
    }
}
