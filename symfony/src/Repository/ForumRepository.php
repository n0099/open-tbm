<?php

namespace App\Repository;

use App\Entity\Forum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;

class ForumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Forum::class);
    }

    public function getOrderedForumsId(): array
    {
        return $this->getEntityManager()
            ->createQuery(/** @lang DQL */'SELECT t.fid FROM App\Entity\Forum t ORDER BY t.fid')
            ->getSingleColumnResult();
    }

    public function isForumExists(int $fid): bool
    {
        return $this->getEntityManager()
            ->createQuery(/** @lang DQL */'SELECT 1 FROM App\Entity\Forum t WHERE t.fid = :fid')
            ->setParameter('fid', $fid)
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR) === 1;
    }
}
