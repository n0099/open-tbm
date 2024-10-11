<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @template T
 * @extends ServiceEntityRepository<T>
 */
#[Exclude]
class BaseRepository extends ServiceEntityRepository
{
    protected function createQuery(string $dql): Query
    {
        return $this->getEntityManager()->createQuery($dql);
    }

    protected function createQueryWithSingleParam(string $dql, string $paramName, int|\ArrayAccess $paramValue): Query
    {
        return $this->createQuery($dql)->setParameter($paramName, $paramValue);
    }

    protected function getQueryResultWithSingleParam(string $dql, string $paramName, int|\ArrayAccess $paramValue): array
    {
        return $this->createQueryWithSingleParam($dql, $paramName, $paramValue)->getResult();
    }

    protected function isEntityExists(string $dql, string $paramName, int|\ArrayAccess $paramValue): bool
    {
        return $this->createQueryWithSingleParam($dql, $paramName, $paramValue)
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR) === 1;
    }
}
