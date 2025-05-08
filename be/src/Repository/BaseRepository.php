<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;

/**
 * @template T
 * @extends ServiceEntityRepository<T>
 */
abstract class BaseRepository extends ServiceEntityRepository
{
    protected function createQuery(string $dql): Query
    {
        return $this->getEntityManager()->createQuery($dql);
    }

    protected function createQueryWithParams(string $dql, array|\ArrayAccess $parameters): Query {
        return $this->createQuery($dql)->setParameters($parameters);
    }

    protected function getQueryResultWithParams(string $dql, array|\ArrayAccess $parameters): array
    {
        return $this->createQueryWithParams($dql, $parameters)->getResult();
    }

    protected function isEntityExists(string $dql, array|\ArrayAccess $parameters): bool
    {
        return $this->createQueryWithParams($dql, $parameters)
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR) === 1;
    }
}
