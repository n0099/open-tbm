<?php

namespace App\Repository;

use App\Entity\Forum;
use Doctrine\Persistence\ManagerRegistry;

/** @extends BaseRepository<Forum> */
class ForumRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Forum::class);
    }

    public function getOrderedForums(): array
    {
        $dql = 'SELECT t FROM App\Entity\Forum t ORDER BY t.fid';
        return $this->createQuery($dql)->getResult();
    }

    public function isForumExists(int $fid): bool
    {
        $dql = 'SELECT 1 FROM App\Entity\Forum t WHERE t.fid = :fid';
        return $this->isEntityExists($dql, ['fid' => $fid]);
    }

    public function getForums(array|\ArrayAccess $fids): array
    {
        $dql = 'SELECT t.fid, t.name FROM App\Entity\Forum t WHERE t.fid IN (:fids)';
        return $this->createQueryWithParams($dql, ['fids' => $fids])->getResult();
    }
}
