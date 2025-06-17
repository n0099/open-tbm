<?php

namespace App\Repository\Post;

use App\Entity\Post\Post;
use App\Repository\BaseRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * @template T of Post
 * @extends BaseRepository<T>
 */
abstract class PostRepository extends BaseRepository
{
    abstract public function selectUnionPostKey(): QueryBuilder;

    abstract public function getPosts(array|\ArrayAccess $postsId): array;
}
