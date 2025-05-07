<?php

namespace App\Repository\Post\Content;

use App\Entity\Post\Content\SubReplyContent;
use App\Repository\Post\PostRepository;

/** @extends PostRepository<SubReplyContent> */
class SubReplyContentRepository extends PostContentRepository
{
    public function getPostsContent(int $fid, array|\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\Content\SubReplyContent t WHERE t.fid = :fid AND t.spid IN (:spid)';
        return $this->getQueryResultWithParams($dql, ['fid' => $fid, 'spid' => $postsId]);
    }
}
