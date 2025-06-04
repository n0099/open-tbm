<?php

namespace App\PostsQuery;

use App\Doctrine\InterpolateParametersSQLOutputWalker;
use App\DTO\PostKey\Reply as ReplyKey;
use App\DTO\PostKey\SubReply as SubReplyKey;
use App\DTO\PostKey\Thread as ThreadKey;
use App\Helper;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use Doctrine\DBAL\Query\UnionType;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Illuminate\Support\Collection;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/** @psalm-import-type PostsKeyByTypePluralName from CursorCodec */
readonly class QueryResult
{
    public int $fid;

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
    public function getQueryResult(AbstractQuery $query, int $limit): array
    {
        $maxResults = $limit + 1;
        $explainJSON = \Safe\json_decode($query->getEntityManager()->getConnection()->executeQuery(
            'EXPLAIN (COSTS, VERBOSE, BUFFERS, FORMAT JSON) ' . $query->getSQL()
        )->fetchOne(), true);
        $plansCost = array_sum(array_map(static fn(array $plan) => $plan['Plan']['Total Cost'], $explainJSON));
        $planCostLimit = $this->containerBag->get('app.query_plan_cost_limit');
        if (!($planCostLimit === null || $planCostLimit === '' || (int)$planCostLimit === 0)) {
            Helper::abortAPIIf(40006, $plansCost > $planCostLimit);
        }

        $result = collect($query->getResult());
        if ($result->count() === $maxResults) {
            $result->pop();
            $hasMorePages = true;
        }
        return [
            'result' => $result,
            'hasMorePages' => $hasMorePages ?? false,
            'queryPlan' => $explainJSON
        ];
    }

    /** @param Collection<Helper::POST_TYPE, QueryBuilder> $queries */
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
                ->addOrderBy('t.' . Helper::POST_TYPE_TO_ID[$postType])
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
            $cursors->mapWithKeys(fn($fieldValue, string $fieldName) =>
                $qb->setParameter("cursor_$fieldName", $fieldValue)); // prevent overwriting existing param
        });
        [
            'unionOfQueriesSQL' => $unionOfQueriesSQL,
            'postsKeyByTypePluralName' => $postsKeyByTypePluralName,
            'hasMorePages' => $hasMorePages,
            'queryPlan' => $queryPlan
        ] = $this->getUnionQueryResult($queries, $isOrderByDesc, $maxResults);

        $this->threads = $postsKeyByTypePluralName->get('threads', collect());
        $this->replies = $postsKeyByTypePluralName->get('replies', collect());
        $this->subReplies = $postsKeyByTypePluralName->get('subReplies', collect());
        $this->fid = $this->threads->first()->fid
            ?? $this->replies->first()->fid
            ?? $this->subReplies->first()->fid;
        $this->currentCursor = $cursorParamValue ?? '';
        $this->nextCursor = $hasMorePages
            ? $this->cursorCodec->encodeNextCursor($postsKeyByTypePluralName->except(
                $queryByPostIDParamsName->map(static fn(string $postID) => Helper::POST_ID_TO_TYPE_PLURAL[$postID])
            ))
            : null;
        $this->query = ['query' => $unionOfQueriesSQL, 'plan' => $queryPlan];

        $this->stopwatch->stop('setResult');
    }

    /**
     * @psalm-type UnionPostKey = array{
     *      postType: 'reply'|'subReply'|'thread',
     *      postId: int,
     *      fid: int,
     *      tid: int,
     *      pid: int,
     *      orderByField: mixed
     * }
     * @param Collection<Helpecr::POST_TYPE, QueryBuilder> $queries
     * @param bool $isOrderByDesc
     * @param int $maxResults
     * @return array{unionOfQueriesSQL: string, postsKeyByTypePluralName: PostsKeyByTypePluralName, hasMorePages: bool, queryPlan: array}
     */
    private function getUnionQueryResult(Collection $queries, bool $isOrderByDesc, int $maxResults): array
    {
        /** @var DBALQueryBuilder $unionOfQueries */
        // https://stackoverflow.com/questions/36959801/doctrine-orm-querybuilder-or-dbal-querybuilder
        $unionOfQueries = $queries->reduce(function (?DBALQueryBuilder $dbalQueryBuilder, QueryBuilder $ormQueryBuilder) {
            $ormQuery = $ormQueryBuilder->getQuery();
            $ormQuery->setHint(\Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER, InterpolateParametersSQLOutputWalker::class);
            $sql = $ormQuery->getSQL();
            if ($dbalQueryBuilder === null) {
                return $ormQueryBuilder->getEntityManager()->getConnection()
                    ->createQueryBuilder()->union($sql);
            }
            return $dbalQueryBuilder->addUnion($sql, UnionType::ALL);
        });
        $firstQuery = $queries->first();

        /** @var array{key-of<UnionPostKey>, string} $firstQueryFieldAliases */
        // field name and aliases in the first query in a union will override any other queries in union
        $firstQueryFieldAliases = array_flip((new Parser($firstQuery->getQuery()))
            ->parse()->getResultSetMapping()->scalarMappings);
        $unionOfQueries = $unionOfQueries
            ->addOrderBy($firstQueryFieldAliases['orderByField'], $isOrderByDesc === true ? 'DESC' : 'ASC')
            ->addOrderBy($firstQueryFieldAliases['postId'])
            ->setMaxResults($maxResults);
        $unionOfQueriesSQL = (new SqlFormatter(new NullHighlighter()))->format($unionOfQueries->getSQL());

        $rsm = new ResultSetMapping();
        foreach ($firstQueryFieldAliases as $fieldName => $fieldAlias) {
            $rsm->addScalarResult($fieldAlias, $fieldName);
        }

        ['result' => $result, 'hasMorePages' => $hasMorePages, 'queryPlan' => $queryPlan] = $this->getQueryResult(
            $firstQuery->getEntityManager()->createNativeQuery($unionOfQueriesSQL, $rsm),
            $this->perPageItems
        );
        /** @var PostsKeyByTypePluralName $postsKeyByTypePluralName */
        $postsKeyByTypePluralName = $result
            ->groupBy(static fn(/** @var UnionPostKey $unionPostKey */ array $unionPostKey) => $unionPostKey['postType'])
            ->mapWithKeys(static fn(Collection $unionPostKeys, /** @var 'reply'|'subReply'|'thread' $postType */ string $postType) =>
                [Helper::POST_TYPE_TO_PLURAL[$postType] => $unionPostKeys
                    ->map(static function (/** @var UnionPostKey $unionPostKey */ array $unionPostKey) use ($postType) {
                        [
                            'postId' => $postId,
                            'fid' => $fid,
                            'tid' => $tid,
                            'pid' => $pid,
                            'orderByField' => $orderByFieldValue
                        ] = $unionPostKey;
                        return match ($postType) {
                            'thread' => new ThreadKey($fid, $postId, $orderByFieldValue),
                            'reply' => new ReplyKey($fid, $tid, $postId, $orderByFieldValue),
                            'subReply' => new SubReplyKey($fid, $tid, $pid, $postId, $orderByFieldValue)
                        };
                    })
                ]);
        Helper::abortAPIIf(40401, $postsKeyByTypePluralName->every(static fn(Collection $i) => $i->isEmpty()));

        return [
            'unionOfQueriesSQL' => $unionOfQueriesSQL,
            'postsKeyByTypePluralName' => $postsKeyByTypePluralName,
            'hasMorePages' => $hasMorePages,
            'queryPlan' => $queryPlan
        ];
    }
}
