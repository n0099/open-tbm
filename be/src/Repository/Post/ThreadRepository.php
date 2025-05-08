<?php

namespace App\Repository\Post;

use App\DTO\PostKey\Thread as ThreadKey;
use App\Entity\Post\Thread;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends PostRepository<Thread> */
class ThreadRepository extends PostRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Thread::class);
    }

    public function selectPostKeyDTO(string $orderByField): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->select('new ' . ThreadKey::class . "(t.tid, '$orderByField', t.$orderByField)");
    }

    public function getPosts(int $fid, array|\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\Thread t WHERE t.fid = :fid AND t.tid IN (:tid)';
        return $this->getQueryResultWithParams($dql, ['fid' => $fid, 'tid' => $postsId]);
    }

    public function isPostExists(int $fid, int $postId): bool
    {
        $dql = 'SELECT 1 FROM App\Entity\Post\Thread t WHERE t.fid = fid AND t.tid = :tid';
        return $this->isEntityExists($dql, ['fid' => $fid, 'tid' => $postId]);
    }

    public function getThreadsIdByChunks(int $fid, int $chunkSize): array
    {
        // https://github.com/doctrine/orm/issues/3542
        // https://github.com/doctrine/dbal/issues/5018#issuecomment-2395177479
        // https://github.com/beberlei/DoctrineExtensions/pull/453
        $entityManager = $this->getEntityManager();
        $tableName = $entityManager->getClassMetadata(Thread::class)->getTableName();
        $statement = $entityManager->getConnection()->prepare(<<<"SQL"
            SELECT tid FROM (
                SELECT fid, tid, ROW_NUMBER() OVER (ORDER BY tid) rn FROM $tableName
            ) t WHERE fid = :fid AND rn % :chunkSize = 0
            SQL);
        $statement->bindValue('fid', $fid);
        $statement->bindValue('chunkSize', $chunkSize);
        return $statement->executeQuery()->fetchFirstColumn();
    }

    public function getThreadsIdWithMaxPostedAtAfter(int $fid, int $after, int $limit): array
    {
        $dql = 'SELECT t.tid,
                    GREATEST(MAX(t.postedAt), MAX(r.postedAt), MAX(sr.postedAt)) maxPostedAt
                FROM App\Entity\Post\Thread t
                    JOIN App\Entity\Post\Reply r WITH r.tid = t.tid
                    JOIN App\Entity\Post\SubReply sr WITH sr.pid = r.pid
                WHERE t.fid = :fid
                    AND r.fid = :fid
                    AND sr.fid = :fid
                    AND t.tid > :after
                GROUP BY t.tid
                ORDER BY t.tid';
        return $this->createQueryWithParams($dql, ['fid' => $fid, 'after' => $after])
            ->setMaxResults($limit)->getResult();
    }
}
