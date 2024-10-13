<?php

namespace App\Repository\Post;

use App\Entity\Post\Post;
use App\Repository\RepositoryWithSplitFid;
use Doctrine\ORM\QueryBuilder;

/**
 * @template T of Post
 * @extends RepositoryWithSplitFid<T>
 */
abstract class PostRepository extends RepositoryWithSplitFid
{
    abstract public function selectPostKeyDTO(string $orderByField): QueryBuilder;

    abstract public function getPosts(array|\ArrayAccess $postsId): array;

    abstract public function isPostExists(int $postId): bool;
}
