<?php

namespace App\Repository\Post;

use App\DTO\PostKey\Reply as ReplyKey;
use App\Entity\Post\Reply;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends PostRepository<Reply> */
class ReplyRepository extends PostRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reply::class);
    }

    public function selectPostKeyDTO(string $orderByField): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->select('new ' . ReplyKey::class . "(t.tid, t.pid, '$orderByField', t.$orderByField)");
    }

    public function getPosts(int $fid, array|\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\Reply t WHERE t.fid = :fid AND t.pid IN (:pid)';
        return $this->getQueryResultWithParams($dql, ['fid' => $fid, 'pid' => $postsId]);
    }

    public function isPostExists(int $fid, int $postId): bool
    {
        $dql = 'SELECT 1 FROM App\Entity\Post\Reply t WHERE t.fid = :fid AND t.pid = :pid';
        return $this->isEntityExists($dql, ['fid' => $fid, 'pid' => $postId]);
    }
}
