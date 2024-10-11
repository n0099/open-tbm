<?php

namespace App\Repository\Post;

use App\Entity\Post\Post;
use App\Helper;
use App\Repository\RepositoryWithSplitFid;
use Doctrine\ORM\QueryBuilder;

/**
 * @template T of Post
 * @extends RepositoryWithSplitFid<T>
 */
abstract class PostRepository extends RepositoryWithSplitFid
{
    public function selectCurrentAndParentPostID(): QueryBuilder
    {
        return $this->createQueryBuilder('t')->select(collect(Helper::POST_TYPE_TO_ID)
            ->slice(0, array_search($this->getTableNameSuffix(), Helper::POST_TYPES, true) + 1)
            ->values()
            ->map(static fn(string $postIDField) => "t.$postIDField")
            ->all());
    }

    abstract public function getPosts(\ArrayAccess $postsId): array;

    abstract public function isPostExists(int $postId): bool;
}
