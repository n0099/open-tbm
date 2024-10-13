<?php

namespace App\Repository\Post;

use App\DTO\PostKey\SubReply as SubReplyKey;
use App\Entity\Post\SubReply;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** @extends PostRepository<SubReply> */
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

    public function selectPostKeyDTO(string $orderByField): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->select('new ' . SubReplyKey::class . "(t.tid, t.pid, t.spid, '$orderByField', t.$orderByField)");
    }

    public function getPosts(array|\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\SubReply t WHERE t.spid IN (:spid)';
        return $this->getQueryResultWithSingleParam($dql, 'spid', $postsId);
    }

    public function isPostExists(int $postId): bool
    {
        $dql = 'SELECT 1 FROM App\Entity\Post\SubReply t WHERE t.spid = :spid';
        return $this->isEntityExists($dql, 'spid', $postId);
    }
}
