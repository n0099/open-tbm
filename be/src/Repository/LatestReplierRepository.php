<?php

namespace App\Repository;

use App\Entity\LatestReplier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Illuminate\Support\Collection;

class LatestReplierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LatestReplier::class);
    }

    public function getLatestRepliersWithoutNameWhenHasUid(\ArrayAccess $latestRepliersId): Collection
    {
        $entityManager = $this->getEntityManager();
        return collect($entityManager->createQuery(<<<'DQL'
                SELECT t.id, t.uid, t.createdAt, t.updatedAt
                FROM App\Entity\LatestReplier t
                WHERE t.id IN (:ids) AND t.uid IS NOT NULL
                DQL) // removeSelect('t.name', 't.displayName')
                ->setParameter('ids', $latestRepliersId)->getResult())
            ->concat(
                $entityManager->createQuery(<<<'DQL'
                SELECT t FROM App\Entity\LatestReplier t
                WHERE t.id IN (:ids) AND t.uid IS NULL
                DQL)->setParameter('ids', $latestRepliersId)->getResult(),
            );
    }
}
