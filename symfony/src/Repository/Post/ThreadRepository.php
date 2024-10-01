<?php

namespace App\Repository\Post;

use App\Entity\Post\Thread;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Thread>
 */
class ThreadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, int $fid)
    {
        parent::__construct($registry, Thread::class);
        $entityManager->getClassMetadata(Thread::class)->setPrimaryTable(['name' => "tbmc_f{$fid}_thread"]);
    }
}
