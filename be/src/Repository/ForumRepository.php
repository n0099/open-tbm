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

    public function getOrderedForumsId(): array
    {
        $dql = 'SELECT t.fid FROM App\Entity\Forum t ORDER BY t.fid';
        return $this->createQuery($dql)->getSingleColumnResult();
    }

    public function isForumExists(int $fid): bool
    {
        $dql = 'SELECT 1 FROM App\Entity\Forum t WHERE t.fid = :fid';
        return $this->isEntityExists($dql, 'fid', $fid);
    }

    public function getForum(int $fid): Forum
    {
        $dql = 'SELECT t.fid, t.name FROM App\Entity\Forum t WHERE t.fid = :fid';
        return $this->createQueryWithSingleParam($dql, 'fid', $fid)
            ->setMaxResults(1)->getSingleResult();
    }
}
