<?php

namespace App\Repository\Post;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class PostRepositoryFactory
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function newThread(int $fid): ThreadRepository
    {
        return new ThreadRepository($this->registry, $this->entityManager, $fid);
    }
}
