<?php

namespace App\Repository\Post\Content;

use App\Entity\Post\Content\ReplyContent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** @extends PostContentRepository<ReplyContent> */
#[Exclude]
class ReplyContentRepository extends PostContentRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, int $fid)
    {
        parent::__construct($registry, $entityManager, ReplyContent::class, $fid);
    }

    protected function getTableNameSuffix(): string
    {
        return 'reply_content';
    }

    public function getPostsContent(array|\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\Content\ReplyContent t WHERE t.pid IN (:pid)';
        return $this->getQueryResultWithSingleParam($dql, 'pid', $postsId);
    }
}
