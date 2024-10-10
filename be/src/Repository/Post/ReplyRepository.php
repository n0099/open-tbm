<?php

namespace App\Repository\Post;

use App\Entity\Post\Reply;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @extends PostRepository<Reply>
 */
#[Exclude]
class ReplyRepository extends PostRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, int $fid)
    {
        parent::__construct($registry, $entityManager, Reply::class, $fid);
    }

    protected function getTableNameSuffix(): string
    {
        return 'reply';
    }
    
    public function getPosts(\ArrayAccess $postsId): array
    {
        return $this->createQueryWithParam(
            /** @lang DQL */'SELECT t FROM App\Entity\Post\Reply t WHERE t.pid IN (:pid)',
            'pid',
            $postsId
        )->getResult();
    }
    
    public function isPostExists(int $postId): bool
    {
        return $this->isPostExistsWrapper(
            $postId,
            /** @lang DQL */'SELECT 1 FROM App\Entity\Post\Reply t WHERE t.pid = :pid',
            'pid'
        );
    }
}
