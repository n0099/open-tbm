<?php

namespace App\PostsQuery;

use App\Helper;
use App\Repository\ForumRepository;
use App\Repository\Post\PostRepository;
use App\Repository\Post\PostRepositoryFactory;
use App\Repository\UserRepository;
use Doctrine\ORM\QueryBuilder;
use Illuminate\Support\Collection;

readonly class Query extends BaseQuery
{
    public function __construct(
        QueryResult $queryResult,
        PostsTree $postsTree,
        private ForumRepository $forumRepository,
        private PostRepositoryFactory $postRepositoryFactory,
        private UserRepository $userRepository,
    ) {
        parent::__construct($queryResult, $postsTree);
    }

    public function query(QueryParams $params, ?string $cursor): void
    {
        /** @var ?int $fid */
        $fid = $params->getUniqueParamValue('fid');
        if ($fid !== null) {
            Helper::abortAPIIfNot(40406, $this->forumRepository->isForumExists($fid));
        }

        $queryByPostIDParamsName = collect(array_count_values(
            collect($params->pick(...Helper::POST_ID))
                ->filter(static fn(QueryParam $p) => $p->getSub('range') === '=')
                ->map(static fn(QueryParam $p) => $p->name)
                ->toArray()
        )) // we need the next cursor for post type that has multiple param
            ->filter(static fn(int $counts) => $counts === 1)
            ->keys();

        $orderByParam = $params->pick('orderBy')[0];
        $this->setOrderByField($orderByParam->value === 'default' ? 'postedAt' : $orderByParam->value)
            ->setIsOrderByDesc($orderByParam->value === 'default'
                ? $queryByPostIDParamsName->isEmpty()
                : $orderByParam->getSub('direction') === 'DESC');

        $this->queryResult->setResult(
            $this->buildQueries($params),
            $cursor,
            $this->orderByField,
            $this->isOrderByDesc,
            $queryByPostIDParamsName
        );
    }

    /** @return Collection<string, QueryBuilder> key by post type */
    private function buildQueries(QueryParams $params): Collection
    {
        /** @var array<string, array> $cachedUserQueryResult key by param name */
        $cachedUserQueryResult = [];
        return collect($this->postRepositoryFactory->newForumPosts())
            ->only($params->getUniqueParamValue('postTypes'))
            ->map(function (PostRepository $repository) use ($params, &$cachedUserQueryResult): QueryBuilder {
                $query = $repository->selectUnionPostKey();
                foreach ($params->getAll() as $paramIndex => $param) {
                    // even when $cachedUserQueryResult[$param->name] is null
                    // it will still pass as a reference to the array item
                    // that is null at this point, but will be later updated by ref
                    $query = self::applyQueryParamsOnQuery(
                        $query,
                        $param,
                        $paramIndex,
                        $cachedUserQueryResult[$param->name],
                    );
                }
                return $query;
            });
    }

    /**
     * Apply conditions of query params on a query builder that created from posts query builder
     */
    private function applyQueryParamsOnQuery(
        QueryBuilder $query,
        QueryParam $param,
        int $paramIndex,
        ?array &$outCachedUserQueryResult,
    ): QueryBuilder {
        $sqlParamName = "param$paramIndex";
        $name = $param->name;
        $value = $param->value;
        $sub = $param->getAllSub();
        $sub['not'] ??= false;
        $not = $sub['not'] ? 'NOT' : '';
        $notReverse = $sub['not'] ? '' : 'NOT';

        $fieldNameOfNumericParams = [
            'threadViewCount' => 'viewCount',
            'threadShareCount' => 'shareCount',
            'threadReplyCount' => 'replyCount',
            'replySubReplyCount' => 'subReplyCount',
        ][$name] ?? $name;
        $inverseRanges = [
            '<' => '>=',
            '=' => '!=',
            '>' => '<=',
        ];
        if (array_key_exists('range', $sub) && !array_key_exists($sub['range'], $inverseRanges)) {
            throw new \InvalidArgumentException();
        }
        $inverseRangeOfNumericParams = $inverseRanges[$sub['range'] ?? null] ?? null;

        $userTypeOfUserParams = str_starts_with($name, 'author') ? 'author' : 'latestReplier';
        $fieldNameOfUserNameParams = str_ends_with($name, 'DisplayName') ? 'displayName' : 'name';
        $getAndCacheUserQuery =
            static function (QueryBuilder $newQueryWhenCacheMiss) use (&$outCachedUserQueryResult): array {
                // $outCachedUserQueryResult === null means it's the first call
                $outCachedUserQueryResult ??= $newQueryWhenCacheMiss->getQuery()->getResult();
                return $outCachedUserQueryResult;
            };

        $whereBetween = static function (string $field) use ($not, $query, $sqlParamName, $value) {
            $values = explode(',', $value);
            return $query->andWhere("t.$field $not BETWEEN :{$sqlParamName}_0 AND :{$sqlParamName}_1")
                ->setParameter("{$sqlParamName}_0", $values[0])
                ->setParameter("{$sqlParamName}_1", $values[1]);
        };
        return match ($name) {
            // numeric
            'fid', 'tid', 'pid', 'spid',
            'authorUid', 'authorExpGrade', 'latestReplierUid',
            'threadViewCount', 'threadShareCount', 'threadReplyCount', 'replySubReplyCount' =>
                // phpcs:disable Generic.WhiteSpace.ScopeIndent
                match ($sub['range']) {
                    'IN' => $query->andWhere("t.$fieldNameOfNumericParams $not IN (:$sqlParamName)")
                        ->setParameter($sqlParamName, explode(',', $value)),
                    'BETWEEN' => $whereBetween($fieldNameOfNumericParams),
                    default => $query->andWhere(
                        "t.$fieldNameOfNumericParams "
                            . ($sub['not'] ? $inverseRangeOfNumericParams : $sub['range'])
                            . " :$sqlParamName",
                    )->setParameter($sqlParamName, $value),
                },
            // textMatch
            'threadTitle', 'postContent' =>
                self::applyTextMatchParamOnQuery(
                    $query,
                    $name === 'threadTitle' ? 'title' : 'content',
                    $value,
                    $sub,
                    $sqlParamName,
                ),
            // dateTimeRange
            'postedAt', 'latestReplyPostedAt' => $whereBetween($name),
            // array
            'threadProperties' => (static function () use ($notReverse, $query, $value) {
                foreach ($value as $threadProperty) {
                    match ($threadProperty) {
                        'good' => $query->andWhere("t.isGood IS $notReverse NULL"),
                        'sticky' => $query->andWhere("t.stickyType IS $notReverse NULL"),
                    };
                }
                return $query;
            })(),
            'authorName', 'latestReplierName', 'authorDisplayName', 'latestReplierDisplayName' =>
                $query->andWhere("t.{$userTypeOfUserParams}Uid $not IN (:$sqlParamName)")
                    ->setParameter(
                        $sqlParamName,
                        $getAndCacheUserQuery(self::applyTextMatchParamOnQuery(
                            $this->userRepository->createQueryBuilder('t')->select('t.uid'),
                            $fieldNameOfUserNameParams,
                            $value,
                            $sub,
                            $sqlParamName,
                        )),
                    ),
            'authorGender', 'latestReplierGender' => (function () use ($not, $query, $sqlParamName, $value, $userTypeOfUserParams, $getAndCacheUserQuery) {
                $newUserQuery = $this->userRepository->createQueryBuilder('t')->select('t.uid');
                if ($value === 'NULL') {
                    $newUserQuery->where('t.gender IS NULL');
                } else {
                    $newUserQuery
                        ->where("t.gender = :{$sqlParamName}_gender")
                        ->setParameter("{$sqlParamName}_gender", $value);
                }
                return $query
                    ->andWhere("t.{$userTypeOfUserParams}Uid $not IN (:$sqlParamName)")
                    ->setParameter($sqlParamName, $getAndCacheUserQuery($newUserQuery));
            })(),
            'authorManagerType' =>
                $value === 'NULL'
                    ? $query->andWhere("t.authorManagerType IS $not NULL")
                    : $query->andWhere('t.authorManagerType ' . ($sub['not'] ? '!=' : '=') . " :$sqlParamName")
                        ->setParameter($sqlParamName, $value),
            default => $query,
        };
    }

    /** @psalm-param array<string, mixed> $subParams */
    private static function applyTextMatchParamOnQuery(
        QueryBuilder $query,
        string $field,
        string $value,
        array $subParams,
        string $sqlParamName,
    ): QueryBuilder {
        $not = $subParams['not'] === true ? 'NOT' : '';
        if ($subParams['matchBy'] === 'regex') {
            return $query->andWhere("t.$field $not REGEXP :$sqlParamName")->setParameter($sqlParamName, $value);
        }

        // split multiple search keyword by space char when $subParams['spaceSplit'] == true
        foreach ($subParams['spaceSplit'] ? explode(' ', $value) : [$value] as $keywordIndex => $keyword) {
            if ($not === 'NOT') {
                $query = $query->andWhere("t.$field NOT LIKE :{$sqlParamName}_$keywordIndex");
            } else { // not (A or B) <=> not A and not B, following https://en.wikipedia.org/wiki/De_Morgan%27s_laws
                $query = $query->orWhere("t.$field LIKE :{$sqlParamName}_$keywordIndex");
            }
            $query = $query->setParameter(
                "{$sqlParamName}_$keywordIndex",
                $subParams['matchBy'] === 'implicit' ? "%$keyword%" : $keyword,
            );
        }
        return $query;
    }
}
