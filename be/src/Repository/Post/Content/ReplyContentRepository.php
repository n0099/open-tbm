<?php

namespace App\Repository\Post\Content;

use App\Entity\Post\Content\ReplyContent;
use Doctrine\Persistence\ManagerRegistry;

/** @extends PostContentRepository<ReplyContent> */
class ReplyContentRepository extends PostContentRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReplyContent::class);
    }

    public function getPostsContent(int $fid, array|\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\Content\ReplyContent t WHERE t.fid = :fid AND t.pid IN (:pid)';
        return $this->getQueryResultWithParams($dql, ['fid' => $fid, 'pid' => $postsId]);
    }
}
