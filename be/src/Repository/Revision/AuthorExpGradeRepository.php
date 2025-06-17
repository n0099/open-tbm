<?php

namespace App\Repository\Revision;

use App\Doctrine\ConvertORMQueryBuilderToDBAL;
use App\DTO\User\AuthorExpGrade as AuthorExpGradeDTO;
use App\Entity\Revision\AuthorExpGrade;
use App\Repository\BaseRepository;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Illuminate\Support\Collection;

/** @extends BaseRepository<AuthorExpGrade> */
class AuthorExpGradeRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthorExpGrade::class);
    }

    /**
     * @param Collection<int, Collection<int, int>> $authorsIdKeyByFid
     * @return AuthorExpGradeDTO[]
     */
    public function getLatestOfUsers(Collection $authorsIdKeyByFid): array
    {
        $query = $this->createQueryBuilder('t')
            ->select('t.fid', 't.uid', 't.discoveredAt', 't.authorExpGrade')
            ->addSelect('OVER(ROW_NUMBER(), PARTITION BY t.uid ORDER BY t.discoveredAt DESC) AS rn');
        /** @var QueryBuilder $query */
        $query = $authorsIdKeyByFid->reduce(
            fn(QueryBuilder $query, Collection $uids, int $fid) =>
                $query->orWhere($query->expr()->andX(
                    $query->expr()->eq('t.fid', ":fid_$fid"),
                    $query->expr()->in('t.uid', ":fid_{$fid}_uids")
                ))
                    ->setParameter("fid_$fid", $fid)
                    // doctrine cannot infer the right array type from its first element with laravel Collection
                    ->setParameter("fid_{$fid}_uids", $uids->toArray()),
            $query
        );

        return ConvertORMQueryBuilderToDBAL::getDenormalizedResult(
            $this->getEntityManager()->getConnection(),
            $query,
            static fn(DBALQueryBuilder $query, array $fieldAliases) => $query
                ->select('t.*')
                ->where("t.{$fieldAliases['rn']} = 1"),
            AuthorExpGradeDTO::class
        );
    }
}
