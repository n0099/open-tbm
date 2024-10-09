<?php

namespace App\Repository\Post\Content;

use App\Entity\Post\Content\ReplyContent;
use App\Repository\Post\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @extends PostRepository<ReplyContent>
 */
#[Exclude]
class ReplyContentRepository extends PostRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, int $fid)
    {
        parent::__construct($registry, $entityManager, ReplyContent::class, $fid);
    }

    protected function getTableNameSuffix(): string
    {
        return 'reply_content';
    }
}
