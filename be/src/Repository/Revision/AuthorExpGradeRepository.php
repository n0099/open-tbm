<?php

namespace App\Repository\Revision;

use App\DTO\User\AuthorExpGrade as AuthorExpGradeDTO;
use App\Entity\Revision\AuthorExpGrade;
use App\Repository\BaseRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;

/** @extends BaseRepository<AuthorExpGrade> */
class AuthorExpGradeRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthorExpGrade::class);
    }

    public function getLatestOfUsers(int $fid, \ArrayAccess $usersId)
    {
        $entityManager = $this->getEntityManager();
        $tableName = $entityManager->getClassMetadata(AuthorExpGrade::class)->getTableName();

        // ResultSetMappingBuilder->addRootEntityFromClassMetadata() won't work due to case-sensitive quoting
        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(AuthorExpGradeDTO::class, 't');
        $rsm->addFieldResult('t', 'uid', 'uid');
        $rsm->addFieldResult('t', 'discoveredAt', 'discoveredAt');
        $rsm->addFieldResult('t', 'authorExpGrade', 'authorExpGrade');

        return $entityManager->createNativeQuery(<<<"SQL"
            SELECT uid, "discoveredAt", "authorExpGrade" FROM (
                SELECT uid, "discoveredAt", "authorExpGrade",
                    ROW_NUMBER() OVER (PARTITION BY uid ORDER BY "discoveredAt" DESC) AS rn
                FROM $tableName WHERE fid = :fid AND uid IN (:usersId)
            ) t WHERE t.rn = 1
            SQL, $rsm)
        ->setParameters(compact('fid', 'usersId'))->getResult();
    }
}
