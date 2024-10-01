<?php

namespace App\Repository\Post;

use App\Entity\Post\Reply;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends PostRepository<Reply>
 */
class ReplyRepository extends PostRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, int $fid)
    {
        parent::__construct($registry, $entityManager, Reply::class, 'reply', $fid);
    }
}
