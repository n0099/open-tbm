<?php

namespace App\Repository;

use App\Entity\LatestReplier;
use Doctrine\Persistence\ManagerRegistry;
use Illuminate\Support\Collection;

/** @extends BaseRepository<LatestReplier> */
class LatestReplierRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LatestReplier::class);
    }

    /** @return list<array{id: int, uid: int, createdAt: int, updatedAt: int}|LatestReplier> */
    public function getLatestRepliersWithoutNameWhenHasUid(array|\ArrayAccess $latestRepliersId): array
    {
        return [ // removeSelect('t.name', 't.displayName')
            ...$this->getQueryResultWithParams(<<<'DQL'
                SELECT t.id, t.uid, t.createdAt, t.updatedAt
                FROM App\Entity\LatestReplier t
                WHERE t.id IN (:ids) AND t.uid IS NOT NULL
                DQL, ['ids' => $latestRepliersId]),
            ...$this->getQueryResultWithParams(<<<'DQL'
                SELECT t FROM App\Entity\LatestReplier t
                WHERE t.id IN (:ids) AND t.uid IS NULL
                DQL, ['ids' => $latestRepliersId]),
        ];
    }
}
