<?php

namespace App\Repository\Revision;

use App\Doctrine\ConvertORMQueryBuilderToDBAL;
use App\DTO\User\ForumModerator as ForumModeratorDTO;
use App\Entity\Revision\ForumModerator;
use App\Repository\BaseRepository;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Illuminate\Support\Collection;

/** @extends BaseRepository<ForumModerator> */
class ForumModeratorRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumModerator::class);
    }

    /**
     * @param Collection<int, Collection<int, string>> $portraitsKeyByFid
     * @return ForumModeratorDTO[]
     */
    public function getLatestOfUsers(Collection $portraitsKeyByFid): array
    {
        $query = $this->createQueryBuilder('t')
            ->select('t.fid', 't.portrait', 't.discoveredAt', 't.moderatorTypes')
            ->addSelect('OVER(ROW_NUMBER(), PARTITION BY t.portrait ORDER BY t.discoveredAt DESC) AS rn');
        /** @var QueryBuilder $query */
        $query = $portraitsKeyByFid->reduce(
            fn(QueryBuilder $query, Collection $portraits, int $fid)
                => $query->orWhere($query->expr()->andX(
                    $query->expr()->eq('t.fid', ":fid_$fid"),
                    $query->expr()->in('t.portrait', ":fid_{$fid}_portraits"),
                ))
                    ->setParameter("fid_$fid", $fid)
                    // doctrine cannot infer the right array type from its first element with laravel Collection
                    ->setParameter("fid_{$fid}_portraits", $portraits->toArray()),
            $query,
        );

        return ConvertORMQueryBuilderToDBAL::getDenormalizedResult(
            $this->getEntityManager()->getConnection(),
            $query,
            static fn(DBALQueryBuilder $query, array $fieldAliases) => $query
                ->select('t.*')
                ->where("t.{$fieldAliases['rn']} = 1"),
            ForumModeratorDTO::class,
        );
    }
}
