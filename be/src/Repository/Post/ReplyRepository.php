<?php

namespace App\Repository\Post;

use App\DTO\PostKey\Reply as ReplyKey;
use App\Entity\Post\Reply;
use Doctrine\ORM\QueryBuilder;

/** @extends PostRepository<Reply> */
class ReplyRepository extends PostRepository
{
    public function selectPostKeyDTO(string $orderByField): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->select('new ' . ReplyKey::class . "(t.tid, t.pid, '$orderByField', t.$orderByField)");
    }

    public function getPosts(array|\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\Reply t WHERE t.pid IN (:pid)';
        return $this->getQueryResultWithSingleParam($dql, 'pid', $postsId);
    }

    public function isPostExists(int $postId): bool
    {
        $dql = 'SELECT 1 FROM App\Entity\Post\Reply t WHERE t.pid = :pid';
        return $this->isEntityExists($dql, 'pid', $postId);
    }
}
