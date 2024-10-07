<?php

namespace App\PostsQuery;

use App\Repository\Post\PostRepository;
use App\Repository\Post\PostRepositoryFactory;
use App\Repository\UserRepository;
use Doctrine\ORM\QueryBuilder;
use Illuminate\Support\Collection;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class SearchQuery extends BaseQuery
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly Stopwatch $stopwatch,
        private readonly CursorCodec $cursorCodec,
        private readonly PostRepositoryFactory $postRepositoryFactory,
        private readonly UserRepository $userRepository
    ) {
        parent::__construct($serializer, $stopwatch, $cursorCodec, $postRepositoryFactory);
    }

    public function query(QueryParams $params, ?string $cursor): self
    {
        /** @var int $fid */
        $fid = $params->getUniqueParamValue('fid');
        /** @var array<string, array> $cachedUserQueryResult key by param name */
        $cachedUserQueryResult = [];
        /** @var Collection<string, QueryBuilder> $queries key by post type */
        $queries = collect($this->postRepositoryFactory->newForumPosts($fid))
            ->only($params->getUniqueParamValue('postTypes'))
            ->map(function (PostRepository $repository) use ($params, &$cachedUserQueryResult): QueryBuilder {
                $postQuery = $repository->selectCurrentAndParentPostID();
                foreach ($params->omit() as $param) { // omit nothing to get all params
                    // even when $cachedUserQueryResult[$param->name] is null
                    // it will still pass as a reference to the array item
                    // that is null at this point, but will be later updated by ref
                    $postQuery = self::applyQueryParamsOnQuery(
                        $postQuery,
                        $param,
                        $cachedUserQueryResult[$param->name],
                    );
                }
                return $postQuery;
            });

        $orderByParam = $params->pick('orderBy')[0];
        $this->orderByField = $orderByParam->value;
        $this->orderByDesc = $orderByParam->getSub('direction');
        if ($this->orderByField === 'default') {
            $this->orderByField = 'postedAt';
            $this->orderByDesc = true;
        }

        $this->setResult($fid, $queries, $cursor);
        return $this;
    }

    /**
     * Apply conditions of query params on a query builder that created from posts query builder
     */
    private function applyQueryParamsOnQuery(
        QueryBuilder $query,
        QueryParam $param,
        ?array &$outCachedUserQueryResult,
    ): QueryBuilder {
        $name = $param->name;
        $value = $param->value;
        $sub = $param->getAllSub();
        $sub['not'] ??= false;
        $not = $sub['not'] ? 'NOT' : '';
        $notBoolStr = $sub['not'] ? 'true' : 'false';

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
        if (!array_key_exists($sub['range'], $inverseRanges)) {
            throw new \InvalidArgumentException();
        }
        $inverseRangeOfNumericParams = $inverseRanges[$sub['range'] ?? null] ?? null;

        $userTypeOfUserParams = str_starts_with($name, 'author') ? 'author' : 'latestReplier';
        $fieldNameOfUserNameParams = str_ends_with($name, 'DisplayName') ? 'displayName' : 'name';
        $getAndCacheUserQuery =
            static function (QueryBuilder $newQueryWhenCacheMiss) use (&$outCachedUserQueryResult): Collection {
                // $outCachedUserQueryResult === null means it's the first call
                $outCachedUserQueryResult ??= $newQueryWhenCacheMiss->getQuery()->getResult();
                return $outCachedUserQueryResult;
            };

        $whereBetween = static function (string $field) use ($query, $not, $value) {
            $values = explode(',', $value);
            return $query->where("t.$field $not BETWEEN ?0 AND ?1")
                ->setParameter(0, $values[0])
                ->setParameter(1, $values[1]);
        };
        return match ($name) {
            // numeric
            'tid', 'pid', 'spid',
            'authorUid', 'authorExpGrade', 'latestReplierUid',
            'threadViewCount', 'threadShareCount', 'threadReplyCount', 'replySubReplyCount' =>
                match ($sub['range']) {
                    'IN' => $query->where("t.$fieldNameOfNumericParams $not IN (:values)")
                        ->setParameter('values', explode(',', $value)),
                    'BETWEEN' => $whereBetween($fieldNameOfNumericParams),
                    default => $query->where(
                        "t.$fieldNameOfNumericParams "
                            . ($sub['not'] ? $inverseRangeOfNumericParams : $sub['range'])
                            . " :value"
                    )->setParameter('value', $value),
                },
            // textMatch
            'threadTitle', 'postContent' =>
                self::applyTextMatchParamOnQuery($query, $name === 'threadTitle' ? 'title' : 'content', $value, $sub),
            // dateTimeRange
            'postedAt', 'latestReplyPostedAt' =>
                $whereBetween($name),
            // array
            'threadProperties' => static function () use ($not, $notBoolStr, $value, $query) {
                foreach ($value as $threadProperty) {
                    match ($threadProperty) {
                        'good' => $query->where("t.isGood = $notBoolStr"),
                        'sticky' => $query->where("t.stickyType IS $not NULL"),
                    };
                }
                return $query;
            },
            'authorName', 'latestReplierName', 'authorDisplayName', 'latestReplierDisplayName' =>
                $query->where("t.{$userTypeOfUserParams}Uid $not IN (:uids)")
                    ->setParameter(
                        'uids',
                        $getAndCacheUserQuery(self::applyTextMatchParamOnQuery(
                            $this->userRepository->createQueryBuilder('t')->select('t.uid'),
                            $fieldNameOfUserNameParams,
                            $value,
                            $sub,
                        ))
                    ),
            'authorGender', 'latestReplierGender' =>
                $query->where("t.{$userTypeOfUserParams}Uid $not IN (:uids)")
                    ->setParameter(
                        'uids',
                        $getAndCacheUserQuery($this->userRepository->createQueryBuilder('t')
                            ->select('t.uid')
                            ->where('t.gender = :value')->setParameter('value', $value))
                    ),
            'authorManagerType' =>
                $value === 'NULL'
                    ? $query->where("t.authorManagerType IS $not NULL")
                    : $query->where('t.authorManagerType ' . ($sub['not'] ? '!=' : '=') . ' :value')
                        ->setParameter('value', $value),
            default => $query,
        };
    }

    /** @psalm-param array<string, mixed> $subParams */
    private static function applyTextMatchParamOnQuery(
        QueryBuilder $query,
        string $field,
        string $value,
        array $subParams,
    ): QueryBuilder {
        $not = $subParams['not'] === true ? 'NOT' : '';
        if ($subParams['matchBy'] === 'regex') {
            return $query->where("t.$field $not REGEXP :value")->setParameter('value', $value);
        }

        // split multiple search keyword by space char when $subParams['spaceSplit'] == true
        foreach ($subParams['spaceSplit'] ? explode(' ', $value) : [$value] as $keyword) {
            if ($not === 'NOT') {
                $query = $query->andWhere("t.$field NOT LIKE :keyword");
            } else { // not (A or B) <=> not A and not B, following https://en.wikipedia.org/wiki/De_Morgan%27s_laws
                $query = $query->orWhere("t.$field LIKE :keyword");
            }
            $keyword = $subParams['matchBy'] === 'implicit' ? "%$keyword%" : $keyword;
            $query = $query->setParameter('keyword', $keyword);
        }
        return $query;
    }
}
