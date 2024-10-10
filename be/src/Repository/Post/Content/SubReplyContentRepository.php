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
class SubReplyContentRepository extends PostRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, int $fid)
    {
        parent::__construct($registry, $entityManager, SubReplyContent::class, $fid);
    }

    protected function getTableNameSuffix(): string
    {
        return 'subReply_content';
    }
    
    public function getPosts(\ArrayAccess $postsId): array
    {
        return $this->createQueryWithParam(
            /** @lang DQL */'SELECT t FROM App\Entity\Post\Content\SubReplyContent t WHERE t.spid IN (:spid)',
            'spid',
            $postsId
        )->getResult();
    }
}
