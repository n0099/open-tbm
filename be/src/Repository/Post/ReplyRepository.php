<?php

namespace App\Repository\Post;

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

    public function selectUnionPostKey(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->select("'reply' AS postType", 't.pid AS postId', 't.fid', 't.tid', 't.pid');
    }

    public function getPosts(int $fid, array|\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\Reply t WHERE t.fid = :fid AND t.pid IN (:pid)';
        return $this->getQueryResultWithParams($dql, ['fid' => $fid, 'pid' => $postsId]);
    }
}
