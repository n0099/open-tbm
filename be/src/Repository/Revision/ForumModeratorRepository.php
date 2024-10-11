<?php

namespace App\Repository\Revision;

use App\DTO\User\ForumModerator as ForumModeratorDTO;
use App\Entity\Revision\ForumModerator;
use App\Repository\BaseRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;

/** @extends BaseRepository<ForumModerator> */
class ForumModeratorRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumModerator::class);
    }

    public function getLatestOfUsers(int $fid, array|\ArrayAccess $portraits)
    {
        $entityManager = $this->getEntityManager();
        $tableName = $entityManager->getClassMetadata(ForumModerator::class)->getTableName();

        // ResultSetMappingBuilder->addRootEntityFromClassMetadata() won't work due to case-sensitive quoting
        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(ForumModeratorDTO::class, 't');
        $rsm->addFieldResult('t', 'portrait', 'portrait');
        $rsm->addFieldResult('t', 'discoveredAt', 'discoveredAt');
        $rsm->addFieldResult('t', 'moderatorTypes', 'moderatorTypes');

        return $entityManager->createNativeQuery(<<<"SQL"
            SELECT portrait, "discoveredAt", "moderatorTypes" FROM (
                SELECT portrait, "discoveredAt", "moderatorTypes",
                    ROW_NUMBER() OVER (PARTITION BY portrait ORDER BY "discoveredAt" DESC) AS rn
                FROM $tableName WHERE fid = :fid AND portrait IN (:portraits)
            ) t WHERE t.rn = 1
            SQL, $rsm)
        ->setParameters(compact('fid', 'portraits'))->getResult();
    }
}
