<?php

namespace App\PostsQuery;

use App\Doctrine\InterpolateParametersSQLOutputWalker;
use App\Doctrine\PrefixParameterNameSqlOutputWalker;
use App\DTO\PostKey\Reply as ReplyKey;
use App\DTO\PostKey\SubReply as SubReplyKey;
use App\DTO\PostKey\Thread as ThreadKey;
use App\Utils;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use Doctrine\DBAL\Query\UnionType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Illuminate\Support\Collection;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @psalm-import-type PostsKeyByTypePluralName from CursorCodec
 * @psalm-type UnionPostKey = array{
 *       postType: 'reply'|'subReply'|'thread',
 *       postId: int,
 *       fid: int,
 *       tid: int,
 *       pid: int,
 *       orderByField: mixed
 *  }
 */
readonly class QueryResult
{
    /** @var Collection<int, ThreadKey> */
    public Collection $threads;
    /** @var Collection<int, ReplyKey> */
    public Collection $replies;
    /** @var Collection<int, SubReplyKey> */
    public Collection $subReplies;
    public string $currentCursor;
    public ?string $nextCursor;
    public array $query;

    public function __construct(
        private Stopwatch $stopwatch,
        private CursorCodec $cursorCodec,
        private ContainerBagInterface $containerBag,
        private int $perPageItems = 100,
    ) {}

    /** @return array{result: Collection, hasMorePages: bool, queryPlan: array} */
    public function getQueryResult(EntityManagerInterface $entityManager, DBALQueryBuilder|QueryBuilder $queryBuilder, ResultSetMapping $rsm, int $limit): array
    {
        $sql = $queryBuilder instanceof QueryBuilder
            ? $queryBuilder->getQuery()
                ->setHint(\Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER, PrefixParameterNameSqlOutputWalker::class)
                ->getSQL()
            : $queryBuilder->getSQL();
        $query = $entityManager->createNativeQuery($sql, $rsm);
        $explainColumnName = 'QUERY PLAN'; // https://www.postgresql.org/docs/current/using-explain.html
        $explainQuery = $query->getEntityManager()->createNativeQuery(
            'EXPLAIN (COSTS, VERBOSE, BUFFERS, FORMAT JSON) ' . $query->getSQL(),
            new ResultSetMapping()->addScalarResult($explainColumnName, $explainColumnName),
        );
        if ($queryBuilder instanceof DBALQueryBuilder) {
            foreach ($queryBuilder->getParameters() as $name => $value) {
                $query->setParameter($name, $value);
                $explainQuery->setParameter($name, $value);
            }
        } elseif ($queryBuilder instanceof QueryBuilder) {
            foreach ($queryBuilder->getParameters() as $parameter) {
                $query->setParameter($parameter->getName(), $parameter->getValue(), $parameter->getType());
                $explainQuery->setParameter($parameter->getName(), $parameter->getValue(), $parameter->getType());
            }
        }

        $explainResult = \Safe\json_decode($explainQuery->getOneOrNullResult()[$explainColumnName], associative: true);
        $plansCost = array_sum(array_map(static fn(array $plan) => $plan['Plan']['Total Cost'], $explainResult));
        $planCostLimit = $this->containerBag->get('app.query_plan_cost_limit');
        if (!($planCostLimit === null || $planCostLimit === '' || (int) $planCostLimit === 0)) {
            Utils::abortAPIIf(40006, $plansCost > $planCostLimit);
        }

        $result = collect($query->getResult());
        $maxResults = $limit + 1;
        if ($result->count() === $maxResults) {
            $result->pop();
            $hasMorePages = true;
        }
        return [
            'result' => $result,
            'hasMorePages' => $hasMorePages ?? false,
            'queryPlan' => $explainResult,
        ];
    }

    /** @param Collection<Utils::POST_TYPE, QueryBuilder> $queries */
    public function setResult(
        Collection $queries,
        ?string $cursorParamValue,
        string $orderByField,
        bool $isOrderByDesc,
        Collection $queryByPostIDParamsName,
    ): void {
        $this->stopwatch->start('setResult');

        $cursorsKeyByPostType = collect();
        if ($cursorParamValue !== null) {
            $cursorsKeyByPostType = $this->cursorCodec->decodeCursor($cursorParamValue, $orderByField);
            // remove query for post type with an empty encoded cursor ',,'
            $queries = $queries->intersectByKeys($cursorsKeyByPostType);
        }
        $maxResults = $this->perPageItems + 1;

        $queries->each(function (QueryBuilder $qb, string $postType) use ($maxResults, $isOrderByDesc, $orderByField, $cursorsKeyByPostType) {
            $qb->addSelect("t.$orderByField AS orderByField")
                ->addOrderBy("t.$orderByField", $isOrderByDesc === true ? 'DESC' : 'ASC')
                // cursor paginator requires values of orderBy column are unique
                // if not it should fall back to other unique field (here is the post ID primary key)
                // https://use-the-index-luke.com/no-offset
                // https://mysql.rjweb.org/doc.php/pagination
                // https://medium.com/swlh/how-to-implement-cursor-pagination-like-a-pro-513140b65f32
                // https://slack.engineering/evolving-api-pagination-at-slack/
                ->addOrderBy('t.' . Utils::POST_TYPE_TO_ID[$postType])
                ->setMaxResults($maxResults);

            $cursors = $cursorsKeyByPostType->get($postType, collect());
            if ($cursors->isEmpty()) {
                return;
            }
            $comparisons = $cursors->keys()->map(
                fn(string $fieldName): Comparison => $isOrderByDesc
                    ? $qb->expr()->lt("t.$fieldName", ":cursor_$fieldName")
                    : $qb->expr()->gt("t.$fieldName", ":cursor_$fieldName"),
            );
            $qb->andWhere($qb->expr()->orX(...$comparisons));
            $cursors->mapWithKeys(fn($fieldValue, string $fieldName)
                => $qb->setParameter("cursor_$fieldName", $fieldValue)); // prevent overwriting existing param
        });
        [
            'rawSQL' => $rawSQL,
            'postsKeyByTypePluralName' => $postsKeyByTypePluralName,
            'hasMorePages' => $hasMorePages,
            'queryPlan' => $queryPlan,
        ] = $this->getPostQueriesResult($queries, $isOrderByDesc, $maxResults);

        $this->threads = $postsKeyByTypePluralName->get('threads', collect());
        $this->replies = $postsKeyByTypePluralName->get('replies', collect());
        $this->subReplies = $postsKeyByTypePluralName->get('subReplies', collect());
        $this->currentCursor = $cursorParamValue ?? '';
        $this->nextCursor = $hasMorePages
            ? $this->cursorCodec->encodeNextCursor($postsKeyByTypePluralName->except(
                $queryByPostIDParamsName->map(static fn(string $postID) => Utils::POST_ID_TO_TYPE_PLURAL[$postID]),
            ))
            : null;
        $this->query = ['query' => $rawSQL, 'plan' => $queryPlan];

        $this->stopwatch->stop('setResult');
    }

    /**
     * @param Collection<Helpecr::POST_TYPE, QueryBuilder> $queries
     * @param bool $isOrderByDesc
     * @param int $maxResults
     * @return array{rawSQL: string, postsKeyByTypePluralName: PostsKeyByTypePluralName, hasMorePages: bool, queryPlan: array}
     */
    private function getPostQueriesResult(Collection $queries, bool $isOrderByDesc, int $maxResults): array
    {
        $firstQuery = $queries->first();
        /** @var DBALQueryBuilder|QueryBuilder $flattedQueryBuilder */
        $flattedQueryBuilder = $queries->count() === 1
            ? $firstQuery
            // https://stackoverflow.com/questions/36959801/doctrine-orm-querybuilder-or-dbal-querybuilder
            : $queries->reduce(function (?DBALQueryBuilder $dbalQueryBuilder, QueryBuilder $ormQueryBuilder, string $postType) {
                $parameterPrefix = $postType . '_';
                $sql = $ormQueryBuilder->getQuery()
                    ->setHint(\Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER, PrefixParameterNameSqlOutputWalker::class)
                    ->setHint(PrefixParameterNameSqlOutputWalker::HINT_PARAMETER_PREFIX, $parameterPrefix)
                    ->getSQL();
                $unionDbalQueryBuilder = $dbalQueryBuilder === null
                    ? $ormQueryBuilder->getEntityManager()->getConnection()->createQueryBuilder()->union($sql)
                    : $dbalQueryBuilder->addUnion($sql, UnionType::ALL);
                foreach ($ormQueryBuilder->getParameters() as $parameter) {
                    $unionDbalQueryBuilder->setParameter(
                        $parameterPrefix . $parameter->getName(),
                        $parameter->getValue(),
                        $parameter->getType(),
                    );
                }
                return $unionDbalQueryBuilder;
            });

        /** @var array{key-of<UnionPostKey>, string} $firstQueryFieldAliases */
        // field name and aliases in the first query in a union will override any other queries in union
        $firstQueryFieldAliases = array_flip(new Parser($firstQuery->getQuery())
            ->parse()->getResultSetMapping()->scalarMappings);
        $addClausesOnUnionQueryBuilder = static fn(DBALQueryBuilder $queryBuilder) => $queryBuilder
            ->addOrderBy($firstQueryFieldAliases['orderByField'], $isOrderByDesc === true ? 'DESC' : 'ASC')
            ->addOrderBy($firstQueryFieldAliases['postId'])
            ->setMaxResults($maxResults);
        if ($flattedQueryBuilder instanceof DBALQueryBuilder) {
            $flattedQueryBuilder = $addClausesOnUnionQueryBuilder($flattedQueryBuilder);
        }
        $rsm = new ResultSetMapping();
        foreach ($firstQueryFieldAliases as $fieldName => $fieldAlias) {
            $rsm->addScalarResult($fieldAlias, $fieldName);
        }

        ['result' => $result, 'hasMorePages' => $hasMorePages, 'queryPlan' => $queryPlan] = $this->getQueryResult(
            $firstQuery->getEntityManager(),
            $flattedQueryBuilder,
            $rsm,
            $this->perPageItems,
        );

        $interpolateParametersForQueryBuilder = static fn(QueryBuilder $queryBuilder) => $queryBuilder->getQuery()
            ->setHint(\Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER, InterpolateParametersSQLOutputWalker::class)
            ->getSQL();
        $interpolateParametersForDBALQueryBuilder = static function () use ($queries, $addClausesOnUnionQueryBuilder, $interpolateParametersForQueryBuilder) {
            /** @var DBALQueryBuilder $queryBuilder */
            $queryBuilder = $queries->reduce(
                function (?DBALQueryBuilder $dbalQueryBuilder, QueryBuilder $ormQueryBuilder) use ($interpolateParametersForQueryBuilder) {
                    $sql = $interpolateParametersForQueryBuilder($ormQueryBuilder);
                    return $dbalQueryBuilder === null
                        ? $ormQueryBuilder->getEntityManager()->getConnection()->createQueryBuilder()->union($sql)
                        : $dbalQueryBuilder->addUnion($sql, UnionType::ALL);
                },
            );
            return $addClausesOnUnionQueryBuilder($queryBuilder)->getSQL();
        };
        $parametersInterpolatedAndFormattedSQL = new SqlFormatter(new NullHighlighter())->format(match (true) {
            $flattedQueryBuilder instanceof DBALQueryBuilder => $interpolateParametersForDBALQueryBuilder(),
            $flattedQueryBuilder instanceof QueryBuilder => $interpolateParametersForQueryBuilder($flattedQueryBuilder),
        });

        return [
            'rawSQL' => $parametersInterpolatedAndFormattedSQL,
            'postsKeyByTypePluralName' => $this->getPostsKeyByTypePluralName($result),
            'hasMorePages' => $hasMorePages,
            'queryPlan' => $queryPlan,
        ];
    }

    /** @return PostsKeyByTypePluralName */
    public function getPostsKeyByTypePluralName(Collection $queryResult): Collection
    {
        /** @var PostsKeyByTypePluralName $postsKeyByTypePluralName */
        $postsKeyByTypePluralName = $queryResult
            ->groupBy(static fn(/** @var UnionPostKey $unionPostKey */ array $unionPostKey) => $unionPostKey['postType'])
            ->mapWithKeys(static fn(Collection $unionPostKeys, /** @var 'reply'|'subReply'|'thread' $postType */ string $postType)
                => [Utils::POST_TYPE_TO_PLURAL[$postType] => $unionPostKeys
                    ->map(static function (/** @var UnionPostKey $unionPostKey */ array $unionPostKey) use ($postType) {
                        [
                            'postId' => $postId,
                            'fid' => $fid,
                            'tid' => $tid,
                            'pid' => $pid,
                            'orderByField' => $orderByFieldValue,
                        ] = $unionPostKey;
                        return match ($postType) {
                            'thread' => new ThreadKey($fid, $postId, $orderByFieldValue),
                            'reply' => new ReplyKey($fid, $tid, $postId, $orderByFieldValue),
                            'subReply' => new SubReplyKey($fid, $tid, $pid, $postId, $orderByFieldValue),
                        };
                    }),
                ]);
        Utils::abortAPIIf(40401, $postsKeyByTypePluralName->every(static fn(Collection $i) => $i->isEmpty()));
        return $postsKeyByTypePluralName;
    }
}
