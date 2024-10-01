<?php

namespace App\Repository\Post;

use App\Entity\Post\Thread;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends PostRepository<Thread>
 */
class ThreadRepository extends PostRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, int $fid)
    {
        parent::__construct($registry, $entityManager, Thread::class, 'thread', $fid);
    }
}
