<?php

namespace App\Repository\Post;

use App\Entity\Post\Reply;
use App\Entity\Post\SubReply;
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

    public function selectUnionPostKey(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->select("'thread' AS postType", 't.tid AS postId', 't.fid', 't.tid', '0 AS pid');
    }

    public function getPosts(int $fid, array|\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\Thread t WHERE t.fid = :fid AND t.tid IN (:tid)';
        return $this->getQueryResultWithParams($dql, ['fid' => $fid, 'tid' => $postsId]);
    }

    public function getThreadsIdByChunks(int $chunkSize): array
    {
        // https://github.com/doctrine/orm/issues/3542
        // https://github.com/doctrine/dbal/issues/5018#issuecomment-2395177479
        // https://github.com/beberlei/DoctrineExtensions/pull/453
        $entityManager = $this->getEntityManager();
        $tableName = $entityManager->getClassMetadata(Thread::class)->getTableName();
        $statement = $entityManager->getConnection()->prepare(<<<"SQL"
            SELECT fid, tid FROM (
                SELECT fid, tid, ROW_NUMBER() OVER (PARTITION BY fid ORDER BY tid) rn FROM $tableName
            ) t WHERE rn % :chunkSize = 0
            SQL);
        $statement->bindValue('chunkSize', $chunkSize);
        return $statement->executeQuery()->fetchAllAssociative();
    }

    public function getThreadsIdWithMaxPostedAtAfter(int $fid, int $after, int $limit): array
    {
        $entityManager = $this->getEntityManager();
        $threadTable = $entityManager->getClassMetadata(Thread::class)->getTableName();
        $replyTable = $entityManager->getClassMetadata(Reply::class)->getTableName();
        $subReplyTable = $entityManager->getClassMetadata(SubReply::class)->getTableName();
        $statement = $entityManager->getConnection()->prepare(<<<"SQL"
            SELECT t.tid, GREATEST(
                t."postedAt",
                r."maxPostedAt",
                sr."maxPostedAt"
            ) "maxPostedAt"
            FROM $threadTable t
            LEFT JOIN LATERAL (
                SELECT tid, max("postedAt") "maxPostedAt"
                FROM $replyTable
                WHERE tid = t.tid
                GROUP BY tid
            ) r ON r.tid = t.tid
            LEFT JOIN LATERAL (
                SELECT sr.tid, max(sr."postedAt") "maxPostedAt"
                FROM $subReplyTable sr
                WHERE sr.tid = t.tid
                GROUP BY sr.tid
            ) sr ON sr.tid = t.tid
            WHERE t.fid = :fid
              AND t.tid > :after
            ORDER BY t.tid
            LIMIT :limit;
            SQL);
        $statement->bindValue('fid', $fid);
        $statement->bindValue('after', $after);
        $statement->bindValue('limit', $limit);
        return $statement->executeQuery()->fetchAllAssociative();
    }
}
