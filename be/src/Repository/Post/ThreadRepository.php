<?php

namespace App\Repository\Post;

use App\DTO\PostKey\Thread as ThreadKey;
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
        $entityManager = $this->getEntityManager();
        $tableName = $entityManager->getClassMetadata(Thread::class)->getTableName();
        $statement = $entityManager->getConnection()->prepare(<<<"SQL"
            SELECT tid FROM (
                SELECT tid, ROW_NUMBER() OVER (ORDER BY tid) rn FROM $tableName
            ) t WHERE fid = :fid AND rn % :chunkSize = 0
            SQL);
        $statement->bindValue('fid', $fid);
        $statement->bindValue('chunkSize', $chunkSize);
        return $statement->executeQuery()->fetchFirstColumn();
    }

    public function getThreadsIdWithMaxPostedAtAfter(int $fid, int $after, int $limit): array
    {
        $entityManager = $this->getEntityManager();
        $threadTable = $entityManager->getClassMetadata(Thread::class)->getTableName();
        $replyTable = $entityManager->getClassMetadata(Reply::class)->getTableName();
        $subReplyTable = $entityManager->getClassMetadata(SubReply::class)->getTableName();
        $statement = $entityManager->getConnection()->prepare(<<<"SQL"
            SELECT t.tid,
                GREATEST(t."postedAt", MAX(r."postedAt"), MAX(sr."postedAt")) AS "maxPostedAt"
            FROM $threadTable t
                JOIN $replyTable r ON r.tid = t.tid
                JOIN $subReplyTable sr ON sr.pid = r.pid
            WHERE t.fid = :fid
                AND r.fid = :fid
                AND sr.fid = :fid
                AND t.tid > :after
            GROUP BY t.tid
            ORDER BY t.tid
            LIMIT :limit
            SQL);
        $statement->bindValue('fid', $fid);
        $statement->bindValue('after', $after);
        $statement->bindValue('limit', $limit);
        return $statement->executeQuery()->fetchAllAssociative();
    }
}
