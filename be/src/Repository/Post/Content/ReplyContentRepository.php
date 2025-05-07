<?php

namespace App\Repository\Post\Content;

use App\Entity\Post\Content\ReplyContent;

/** @extends PostContentRepository<ReplyContent> */
class ReplyContentRepository extends PostContentRepository
{
    public function getPostsContent(array|\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\Content\ReplyContent t WHERE t.pid IN (:pid)';
        return $this->getQueryResultWithSingleParam($dql, 'pid', $postsId);
    }
}
