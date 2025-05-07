<?php

namespace App\Repository\Post\Content;

use App\Entity\Post\Content\SubReplyContent;
use App\Repository\Post\PostRepository;

/** @extends PostRepository<SubReplyContent> */
class SubReplyContentRepository extends PostContentRepository
{
    public function getPostsContent(array|\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\Content\SubReplyContent t WHERE t.spid IN (:spid)';
        return $this->getQueryResultWithSingleParam($dql, 'spid', $postsId);
    }
}
