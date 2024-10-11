<?php

namespace App\Repository\Post\Content;

use App\Entity\Post\Content\PostContent;
use App\Repository\RepositoryWithSplitFid;

/**
 * @template T of PostContent
 * @extends RepositoryWithSplitFid<T>
 */
abstract class PostContentRepository extends RepositoryWithSplitFid
{
    abstract public function getPostsContent(array|\ArrayAccess $postsId): array;
}
