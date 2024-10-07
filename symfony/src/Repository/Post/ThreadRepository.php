<?php

namespace App\Repository\Post;

use App\Entity\Post\Thread;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @extends PostRepository<Thread>
 */
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

    public function getThreadsIdByChunks(int $chunkSize): array
    {
        // https://github.com/doctrine/orm/issues/3542
        // https://github.com/doctrine/dbal/issues/5018#issuecomment-2395177479
        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        $tableName = $connection->quoteIdentifier($entityManager->getClassMetadata(Thread::class)->getTableName());
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
        return $this->getEntityManager()->createQuery(<<<'DQL'
            SELECT t.tid FROM App\Entity\Post\Thread t WHERE t.tid > :after ORDER BY t.tid
            DQL)->setMaxResults($limit)->setParameters(compact('after'))->getSingleColumnResult();
    }
}
