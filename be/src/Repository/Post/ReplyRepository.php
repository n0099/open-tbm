<?php

namespace App\Repository\Post;

use App\DTO\PostKey\Reply as ReplyKey;
use App\Entity\Post\Reply;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** @extends PostRepository<Reply> */
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

    public function selectPostKeyDTO(string $orderByField): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->select('new ' . ReplyKey::class . "(t.tid, t.pid, '$orderByField', t.$orderByField)");
    }

    public function getPosts(array|\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\Reply t WHERE t.pid IN (:pid)';
        return $this->getQueryResultWithSingleParam($dql, 'pid', $postsId);
    }

    public function isPostExists(int $postId): bool
    {
        $dql = 'SELECT 1 FROM App\Entity\Post\Reply t WHERE t.pid = :pid';
        return $this->isEntityExists($dql, 'pid', $postId);
    }
}
