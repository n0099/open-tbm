<?php

namespace App\Repository\Post;

use App\Entity\Post\Thread;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** @extends PostRepository<Thread> */
#[Exclude]
class ThreadRepository extends PostRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, int $fid)
    {
        parent::__construct($registry, $entityManager, Thread::class, $fid);
    }

    protected function getTableNameSuffix(): string
    {
        return 'thread';
    }

    public function getPosts(array|\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\Thread t WHERE t.tid IN (:tid)';
        return $this->getQueryResultWithSingleParam($dql, 'tid', $postsId);
    }

    public function isPostExists(int $postId): bool
    {
        $dql = 'SELECT 1 FROM App\Entity\Post\Thread t WHERE t.tid = :tid';
        return $this->isEntityExists($dql, 'tid', $postId);
    }

    public function getThreadsIdByChunks(int $chunkSize): array
    {
        // https://github.com/doctrine/orm/issues/3542
        // https://github.com/doctrine/dbal/issues/5018#issuecomment-2395177479
        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        $tableName = $entityManager->getClassMetadata(Thread::class)->getTableName();
        $statement = $connection->prepare(<<<"SQL"
            SELECT tid FROM (
                SELECT tid, ROW_NUMBER() OVER (ORDER BY tid) rn FROM $tableName
            ) t WHERE rn % :chunkSize = 0
            SQL);
        $statement->bindValue('chunkSize', $chunkSize);
        return $statement->executeQuery()->fetchFirstColumn();
    }

    public function getThreadsIdAfter(int $after, int $limit): array
    {
        $dql = 'SELECT t.tid FROM App\Entity\Post\Thread t WHERE t.tid > :after ORDER BY t.tid';
        return $this->createQuery($dql)->setMaxResults($limit)
            ->setParameters(compact('after'))->getSingleColumnResult();
    }
}
