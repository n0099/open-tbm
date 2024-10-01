<?php

namespace App\Repository\Post\Content;

use App\Entity\Post\Content\ReplyContent;
use App\Repository\Post\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends PostRepository<ReplyContent>
 */
class ReplyContentRepository extends PostRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, int $fid)
    {
        parent::__construct($registry, $entityManager, ReplyContent::class, 'reply_content', $fid);
    }
}
