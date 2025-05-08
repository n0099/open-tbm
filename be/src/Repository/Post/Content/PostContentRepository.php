<?php

namespace App\Repository\Post\Content;

use App\Entity\Post\Content\PostContent;
use App\Repository\BaseRepository;

/**
 * @template T of PostContent
 * @extends BaseRepository<T>
 */
abstract class PostContentRepository extends BaseRepository
{
    abstract public function getPostsContent(int $fid, array|\ArrayAccess $postsId): array;
}
