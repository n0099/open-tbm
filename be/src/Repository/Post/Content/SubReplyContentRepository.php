<?php

namespace App\Repository\Post\Content;

use App\Entity\Post\Content\SubReplyContent;
use App\Repository\Post\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @extends PostRepository<SubReplyContent>
 */
#[Exclude]
class SubReplyContentRepository extends PostContentRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, int $fid)
    {
        parent::__construct($registry, $entityManager, SubReplyContent::class, $fid);
    }

    protected function getTableNameSuffix(): string
    {
        return 'subReply_content';
    }
    
    public function getPostsContent(\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\Content\SubReplyContent t WHERE t.spid IN (:spid)';
        return $this->getQueryResultWithSingleParam($dql, 'spid', $postsId);
    }
}
