<?php

namespace App\Repository\Post;

use App\DTO\PostKey\SubReply as SubReplyKey;
use App\Entity\Post\SubReply;
use Doctrine\ORM\QueryBuilder;

/** @extends PostRepository<SubReply> */
class SubReplyRepository extends PostRepository
{
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
