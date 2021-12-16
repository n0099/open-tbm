<?php

namespace App\Http\PostsQuery;

use App\Helper;
use App\Tieba\Eloquent\UserModel;
use App\Tieba\Eloquent\PostModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class SearchQuery
{
    use BaseQuery;

    private static function getParamName(array $param): string
    {
        return (string)array_keys($param)[0];
    }

    public function query(QueryParams $queryParams): self
    {
        $getUniqueParamValue = static fn (string $name) =>
            Arr::first($queryParams, static fn (array $param): bool => self::getParamName($param) === $name)[$name];
        $postQueries = [];
        foreach (Arr::only(
            PostModelFactory::getPostModelsByFid($getUniqueParamValue('fid')),
            $getUniqueParamValue('postTypes')
        ) as $postType => $postModel) {
            $postQuery = $postModel->newQuery();
            foreach ($queryParams as $param) {
                $postQueries[$postType] = self::applyQueryParamsOnQuery($postQuery, $param);
            }
        }

        $postsQueriedInfo = array_merge(
            ['fid' => $getUniqueParamValue('fid')],
            array_fill_keys(Helper::POST_TYPES, [])
        );
        foreach ($postQueries as $postType => $postQuery) {
            $postQueries[$postType] = $postQuery->hidePrivateFields()->simplePaginate($this->perPageItems);
            $postsQueriedInfo[$postType] = $postQueries[$postType]->toArray()['data'];
        }
        Helper::abortAPIIf(40401, array_keys(array_filter($postsQueriedInfo)) === ['fid']);

        $this->queryResult = $postsQueriedInfo;
        $this->queryResultPages = [ // todo: should cast simplePagination to array in $postQueries to prevent dynamic call method by string
            'firstItem' => self::pageInfoUnion($postQueries, 'firstItem', static fn (array $unionValues) => min($unionValues)),
            'itemsCount' => self::pageInfoUnion($postQueries, 'count', static fn (array $unionValues) => array_sum($unionValues)),
            'currentPage' => self::pageInfoUnion($postQueries, 'currentPage', static fn (array $unionValues) => min($unionValues))
        ];

        return $this;
    }

    /**
     * Union builders pagination $unionMethodName data by $unionStatement
     *
     * @param array $queryBuilders
     * @param string $unionMethodName
     * @param callable $unionStatement
     * @return mixed $unionStatement()
     */
    private static function pageInfoUnion(array $queryBuilders, string $unionMethodName, callable $unionStatement): mixed
    {
        $unionValues = [];
        foreach ($queryBuilders as $queryBuilder) {
            $unionValues[] = $queryBuilder->$unionMethodName();
        }
        $unionValues = array_filter($unionValues); // array_filter() will remove falsy values
        return $unionStatement($unionValues === [] ? [0] : $unionValues); // prevent empty array
    }

    /**
     * Apply conditions of query params on a query builder that created from posts model
     */
    private static function applyQueryParamsOnQuery(Builder $qb, Param $param): Builder
    {
        $name = $param->name;
        $value = $param->value;
        $sub = $param->getAllSub();
        $sub['not'] ??= false;
        $not = $sub['not'] ? 'Not' : '';
        $inverseNot = $sub['not'] ? '' : 'Not';

        $fieldNameOfNumericParams = [
            'threadViewNum' => 'viewNum',
            'threadShareNum' => 'shareNum',
            'threadReplyNum' => 'replyNum',
            'replySubReplyNum' => 'subReplyNum'
        ][$name] ?? $name;
        $inverseRangeOfNumericParams = [
            '<' => '>=',
            '=' => '!=',
            '>' => '<='
        ][$sub['range'] ?? null] ?? null;
        $userTypeOfUserParams = str_starts_with($name, 'author') ? 'author' : 'latestReplier';
        $fieldNameOfUserNameParams = str_ends_with($name, 'DisplayName') ? 'displayName' : 'name';

        return match ($name) {
            // unique
            'orderBy' => $value === 'default'
                ? $qb->orderByDesc('postTime')
                : $qb->orderBy($value, $sub['direction']),
            // numeric
            'tid', 'pid', 'spid',
            'authorUid', 'authorExpGrade', 'latestReplierUid',
            'threadViewNum', 'threadShareNum', 'threadReplyNum', 'replySubReplyNum' =>
                $sub['range'] === 'IN' || $sub['range'] === 'BETWEEN'
                    ? $qb->{"where{$not}{$sub['range']}"}($fieldNameOfNumericParams, explode(',', $value))
                    : $qb->where(
                        $fieldNameOfNumericParams,
                        $sub['not'] ? $inverseRangeOfNumericParams : $sub['range'],
                        $value
                    ),
            // textMatch
            'threadTitle', 'postContent' =>
                self::applyTextMatchParamsOnQuery($qb, $name === 'threadTitle' ? 'title' : 'content', $not, $value, $sub),
            // dateTimeRange
            'postTime', 'latestReplyTime' =>
                $qb->{"where{$not}Between"}($name, explode(',', $value)),
            // array
            'threadProperties' => static function () use ($sub, $inverseNot, $value, $qb) {
                foreach ($value as $threadProperty) {
                    match ($threadProperty) {
                        'good' => $qb->where('isGood', !$sub['not']),
                        'sticky' => $qb->{"where{$inverseNot}Null"}('stickyType')
                    };
                }
                return $qb;
            },
            // user
            'authorName', 'latestReplierName', 'authorDisplayName', 'latestReplierDisplayName' =>
                $qb->{"where{$not}In"}(
                    "{$userTypeOfUserParams}Uid",
                    self::applyTextMatchParamsOnQuery(UserModel::newQuery(), $fieldNameOfUserNameParams, $not, $value, $sub)
                ),
            'authorGender', 'latestReplierGender' =>
                $qb->{"where{$not}In"}("{$userTypeOfUserParams}Uid", UserModel::where('gender', $value)),
            'authorManagerType' =>
                $value === 'NULL'
                    ? $qb->{"where{$not}Null"}('authorManagerType')
                    : $qb->where('authorManagerType', $sub['not'] ? '!=' : '=', $value),
            default => $qb
        };
    }

    private static function applyTextMatchParamsOnQuery(Builder $qb, string $field, string $value, array $subParams): Builder
    {
        $not = $subParams['not'] ? 'Not' : '';
        if ($subParams['matchBy'] === 'regex') {
            return $qb->where($field, "{$not} REGEXP", $value);
        }
        return $qb->where(static function ($subQuery) use ($subParams, $field, $not, $value) {
            // not (A or B) <=> not A and not B, following https://en.wikipedia.org/wiki/De_Morgan%27s_laws
            $isOrWhere = $not === 'Not' ? '' : 'or';
            $addMatchKeyword = static fn (string $keyword) =>
                $subQuery->{"{$isOrWhere}Where"}(
                    $field,
                    "{$not} LIKE",
                    $subParams['matchBy'] === 'implicit' ? "%{$keyword}%" : $keyword
                );
            if ($subParams['spaceSplit']) { // split multiple search keyword by space char
                foreach (explode(' ', $value) as $splitedKeyword) {
                    $addMatchKeyword($splitedKeyword);
                }
            } else {
                $addMatchKeyword($value);
            }
        });
    }
}
