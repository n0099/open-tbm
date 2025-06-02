<?php

namespace App\Repository\Post;

use App\Entity\Post\SubReply;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends PostRepository<SubReply> */
class SubReplyRepository extends PostRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubReply::class);
    }

    public function selectUnionPostKey(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->select("'subReply' AS postType", 't.spid AS postId', 't.fid', 't.tid', 't.pid');
    }

    public function getPosts(int $fid, array|\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\SubReply t WHERE t.fid = :fid AND t.spid IN (:spid)';
        return $this->getQueryResultWithParams($dql, ['fid' => $fid, 'spid' => $postsId]);
    }
}
