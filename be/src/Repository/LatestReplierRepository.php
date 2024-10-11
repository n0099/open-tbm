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

    public function getLatestRepliersWithoutNameWhenHasUid(\ArrayAccess $latestRepliersId): Collection
    {
        // removeSelect('t.name', 't.displayName')
        return collect($this->getQueryResultWithSingleParam(<<<'DQL'
                SELECT t.id, t.uid, t.createdAt, t.updatedAt
                FROM App\Entity\LatestReplier t
                WHERE t.id IN (:ids) AND t.uid IS NOT NULL
                DQL, 'ids', $latestRepliersId))
            ->concat(
                $this->getQueryResultWithSingleParam(<<<'DQL'
                SELECT t FROM App\Entity\LatestReplier t
                WHERE t.id IN (:ids) AND t.uid IS NULL
                DQL, 'ids', $latestRepliersId),
            );
    }
}
