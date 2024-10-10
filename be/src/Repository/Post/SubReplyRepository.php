<?php

namespace App\Repository\Post;

use App\Entity\Post\SubReply;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @extends PostRepository<SubReply>
 */
#[Exclude]
class SubReplyRepository extends PostRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, int $fid)
    {
        parent::__construct($registry, $entityManager, SubReply::class, $fid);
    }

    protected function getTableNameSuffix(): string
    {
        return 'subReply';
    }
    
    public function getPosts(\ArrayAccess $postsId): array
    {
        return $this->createQueryWithParam(
            /** @lang DQL */'SELECT t FROM App\Entity\Post\SubReply t WHERE t.spid IN (:spid)',
            'spid',
            $postsId
        )->getResult();
    }

    public function isPostExists(int $postId): bool
    {
        return $this->isPostExistsWrapper(
            $postId,
            /** @lang DQL */'SELECT 1 FROM App\Entity\Post\SubReply t WHERE t.spid = :spid',
            'spid'
        );
    }
}
