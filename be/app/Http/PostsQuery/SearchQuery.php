<?php

namespace App\Http\PostsQuery;

use App\Helper;
use App\Tieba\Eloquent\PostModel;
use App\Tieba\Eloquent\UserModel;
use App\Tieba\Eloquent\PostModelFactory;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

class SearchQuery
{
    use BaseQuery;

    public function query(QueryParams $params): self
    {
        /** @var int $fid */
        $fid = $params->getUniqueParamValue('fid');
        /** @var array<string, Collection> $cachedUserQuery keyed by param name */
        $cachedUserQuery = [];
        /** @var Collection<string, Builder> $queries keyed by post type */
        $queries = collect(PostModelFactory::getPostModelsByFid($fid))
            ->only($params->getUniqueParamValue('postTypes'))
            ->map(function (PostModel $postModel, string $postType) use ($params, &$cachedUserQuery): Builder {
                $postQuery = $postModel->newQuery();
                foreach ($params->omit() as $param) { // omit nothing to get all params
                    // even when $cachedUserQuery[$param->name] is null, it will still pass as a reference to the array item
                    // which is null at this point, but will be later updated by ref
                    self::applyQueryParamsOnQuery($postQuery, $param, $cachedUserQuery[$param->name]);
                }
                return $postQuery->selectCurrentAndParentPostID();
            });
        /** @var Collection<string, CursorPaginator> $paginators keyed by post type */
        $paginators = $queries->map(fn (Builder $qb) => $qb->cursorPaginate($this->perPageItems));
        $this->setResult($fid, $paginators, $paginators
            ->mapWithKeys(static fn (CursorPaginator $paginator, string $type) =>
                [Helper::POST_TYPE_TO_PLURAL[$type] => $paginator->collect()]
            ));

        return $this;
    }

    /**
     * Apply conditions of query params on a query builder that created from posts model
     */
    private static function applyQueryParamsOnQuery(Builder $qb, Param $param, ?Collection &$outCachedUserQueryResult): Builder
    {
        $name = $param->name;
        $value = $param->value;
        $sub = $param->getAllSub();
        $sub['not'] ??= false;
        $not = $sub['not'] ? 'Not' : '';
        $inverseNot = $sub['not'] ? '' : 'Not';

        $fieldNameOfNumericParams = [
            'threadViewCount' => 'viewCount',
            'threadShareCount' => 'shareCount',
            'threadReplyCount' => 'replyCount',
            'replySubReplyCount' => 'subReplyCount'
        ][$name] ?? $name;
        $inverseRangeOfNumericParams = [
            '<' => '>=',
            '=' => '!=',
            '>' => '<='
        ][$sub['range'] ?? null] ?? null;

        $userTypeOfUserParams = str_starts_with($name, 'author') ? 'author' : 'latestReplier';
        $fieldNameOfUserNameParams = str_ends_with($name, 'DisplayName') ? 'displayName' : 'name';
        $getAndCacheUserQuery = static function (BuilderContract $newQueryWhenCacheMiss) use (&$outCachedUserQueryResult): Collection {
            // $outCachedUserQueryResult === null means it's the first call
            $outCachedUserQueryResult ??= $newQueryWhenCacheMiss->get();
            return $outCachedUserQueryResult;
        };

        return match ($name) {
            // unique
            'orderBy' => $value === 'default'
                ? $qb->orderByDesc('postTime')
                : $qb->orderBy($value, $sub['direction']),
            // numeric
            'tid', 'pid', 'spid',
            'authorUid', 'authorExpGrade', 'latestReplierUid',
            'threadViewCount', 'threadShareCount', 'threadReplyCount', 'replySubReplyCount' =>
                $sub['range'] === 'IN' || $sub['range'] === 'BETWEEN'
                    ? $qb->{"where$not{$sub['range']}"}($fieldNameOfNumericParams, explode(',', $value))
                    : $qb->where(
                        $fieldNameOfNumericParams,
                        $sub['not'] ? $inverseRangeOfNumericParams : $sub['range'],
                        $value
                    ),
            // textMatch
            'threadTitle', 'postContent' =>
                self::applyTextMatchParamOnQuery($qb, $name === 'threadTitle' ? 'title' : 'content', $value, $sub),
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
            'authorName', 'latestReplierName', 'authorDisplayName', 'latestReplierDisplayName' =>
                $qb->{"where{$not}In"}(
                    "{$userTypeOfUserParams}Uid",
                    $getAndCacheUserQuery(self::applyTextMatchParamOnQuery(
                        UserModel::select('uid'), $fieldNameOfUserNameParams, $value, $sub
                    ))
                ),
            'authorGender', 'latestReplierGender' =>
                $qb->{"where{$not}In"}(
                    "{$userTypeOfUserParams}Uid",
                    $getAndCacheUserQuery(UserModel::select('uid')->where('gender', $value))
                ),
            'authorManagerType' =>
                $value === 'NULL'
                    ? $qb->{"where{$not}Null"}('authorManagerType')
                    : $qb->where('authorManagerType', $sub['not'] ? '!=' : '=', $value),
            default => $qb
        };
    }

    private static function applyTextMatchParamOnQuery(BuilderContract $qb, string $field, string $value, array $subParams): BuilderContract
    {
        $not = $subParams['not'] ? 'Not' : '';
        if ($subParams['matchBy'] === 'regex') {
            return $qb->where($field, "$not REGEXP", $value);
        }
        return $qb->where(static function (Builder $subQuery) use ($subParams, $field, $not, $value) {
            // not (A or B) <=> not A and not B, following https://en.wikipedia.org/wiki/De_Morgan%27s_laws
            $isOrWhere = $not === 'Not' ? '' : 'or';
            $addMatchKeyword = static fn (string $keyword) =>
                $subQuery->{"{$isOrWhere}Where"}(
                    $field,
                    trim("$not LIKE"),
                    $subParams['matchBy'] === 'implicit' ? "%$keyword%" : $keyword
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
