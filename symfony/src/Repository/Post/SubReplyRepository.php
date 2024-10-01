<?php

namespace App\Repository\Post;

use App\Entity\Post\SubReply;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends PostRepository<SubReply>
 */
class SubReplyRepository extends PostRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, int $fid)
    {
        parent::__construct($registry, $entityManager, SubReply::class, 'subReply', $fid);
    }
}
