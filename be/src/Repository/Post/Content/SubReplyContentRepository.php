<?php

namespace App\Repository\Post\Content;

use App\Entity\Post\Content\SubReplyContent;
use App\Repository\Post\PostRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends PostRepository<SubReplyContent> */
class SubReplyContentRepository extends PostContentRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubReplyContent::class);
    }

    public function getPostsContent(array|\ArrayAccess $postsId): array
    {
        $dql = 'SELECT t FROM App\Entity\Post\Content\SubReplyContent t WHERE t.spid IN (:spid)';
        return $this->getQueryResultWithParams($dql, ['spid' => $postsId]);
    }
}
