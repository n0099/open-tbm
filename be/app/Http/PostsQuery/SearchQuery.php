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
                $postQueries[$postType] = self::applySearchQueryOnPostModel($postQuery, $param);
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
     * Apply search query param's condition on posts model's query builder
     *
     * @param Builder $postQuery
     * @param array $param
     * @return Builder
     */
    private static function applySearchQueryOnPostModel(Builder $postQuery, array $param): Builder
    {
        $paramName = self::getParamName($param);
        $paramValue = $param[$paramName];

        $numericParamsReverseRange = [
            '<' => '>=',
            '=' => '!=',
            '>' => '<='
        ];
        $not = $param['not'] ?? false ? 'Not' : ''; // unique params doesn't have not sub param
        $reverseNot = $param['not'] ?? false ? '' : 'Not';
        switch ($paramName) {
            // uniqueParams
            case 'orderBy':
                if ($paramValue === 'default') {
                    return $postQuery->orderByDesc('postTime');
                }
                return $postQuery->orderBy($paramValue, $param['direction']);
                break;
            // numericParams
            case 'tid':
            case 'pid':
            case 'spid':
            case 'authorUid':
            case 'authorExpGrade':
            case 'latestReplierUid':
            case 'threadViewNum':
            case 'threadShareNum':
            case 'threadReplyNum':
            case 'replySubReplyNum':
                $fieldsName = [
                    'threadViewNum' => 'viewNum',
                    'threadShareNum' => 'shareNum',
                    'threadReplyNum' => 'replyNum',
                    'replySubReplyNum' => 'subReplyNum'
                ];
                if ($param['range'] === 'IN' || $param['range'] === 'BETWEEN') {
                    $cause = $not . $param['range'];
                    return $postQuery->{"where{$cause}"}($fieldsName[$paramName] ?? $paramName, explode(',', $paramValue));
                }
                return $postQuery->where($fieldsName[$paramName] ?? $paramName, $param['not'] ? $numericParamsReverseRange[$param['range']] : $param['range'], $paramValue);
            // textMatchParams
            case 'threadTitle':
            case 'postContent':
                return self::applyTextMatchParamsQuery($postQuery, $paramName === 'threadTitle' ? 'title' : 'content', $not, $paramValue, $param);

            case 'postTime':
            case 'latestReplyTime':
                return $postQuery->{"where{$not}Between"}($paramName, explode(',', $paramValue));

            case 'threadProperties':
                foreach ($paramValue as $threadProperty) {
                    switch ($threadProperty) {
                        case 'good':
                            $postQuery = $postQuery->where('isGood', $param['not'] ? false : true);
                            break;
                        case 'sticky':
                            $postQuery = $postQuery->{"where{$reverseNot}Null"}('stickyType');
                            break;
                    }
                }
                return $postQuery;

            case 'authorName':
            case 'latestReplierName':
            case 'authorDisplayName':
            case 'latestReplierDisplayName':
                $userType = str_starts_with($paramName, 'author') ? 'author' : 'latestReplier';
                $fieldName = str_ends_with($paramName, 'DisplayName') ? 'displayName' : 'name';
                return $postQuery->{"where{$not}In"}("{$userType}Uid", self::applyTextMatchParamsQuery(UserModel::newQuery(), $fieldName, $not, $paramValue, $param));
            case 'authorGender':
            case 'latestReplierGender':
                $userType = str_starts_with($paramName, 'author') ? 'author' : 'latestReplier';
                return $postQuery->{"where{$not}In"}("{$userType}Uid", UserModel::where('gender', $paramValue));
            case 'authorManagerType':
                if ($paramValue === 'NULL') {
                    return $postQuery->{"where{$not}Null"}('authorManagerType');
                }
                return $postQuery->where('authorManagerType', $param['not'] ? '!=' : '=', $paramValue);
            default:
                return $postQuery;
        }
    }

    private static function applyTextMatchParamsQuery(Builder $query, string $fieldName, string $notString, $paramValue, array $param): Builder
    {
        if ($param['matchBy'] === 'regex') {
            return $query->where($fieldName, "{$notString} REGEXP", $paramValue);
        }
        return $query->where(static function ($query) use ($param, $fieldName, $notString, $paramValue) {
            // not (A or B) <=> not A and not B, following https://en.wikipedia.org/wiki/De_Morgan%27s_laws
            $notOrWhere = $notString === 'Not' ? '' : 'or';
            $addMatchKeyword = static fn (string $keyword) =>
            $query->{"{$notOrWhere}Where"}(
                $fieldName,
                "{$notString} LIKE",
                $param['matchBy'] === 'implicit' ? "%{$keyword}%" : $keyword
            );
            if ($param['spaceSplit']) {
                foreach (explode(' ', $paramValue) as $splitedKeyword) { // split multiple search keyword by space char
                    $addMatchKeyword($splitedKeyword);
                }
            } else {
                $addMatchKeyword($paramValue);
            }
        });
    }
}
