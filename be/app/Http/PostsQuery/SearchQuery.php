<?php

namespace App\Http\PostsQuery;

use App\Eloquent\Model\Post\Post;
use App\Eloquent\Model\Post\PostFactory;
use App\Eloquent\Model\User;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SearchQuery extends BaseQuery
{
    public function query(QueryParams $params, ?string $cursor): self
    {
        /** @var int $fid */
        $fid = $params->getUniqueParamValue('fid');
        /** @var array<string, Collection<string, Builder<User>>> $cachedUserQuery key by param name */
        $cachedUserQuery = [];
        /** @var Collection<string, Builder<Post>> $queries key by post type */
        $queries = collect(PostFactory::getPostModelsByFid($fid))
            ->only($params->getUniqueParamValue('postTypes'))
            ->map(function (Post $postModel) use ($params, &$cachedUserQuery): Builder {
                $postQuery = $postModel->newQuery();
                foreach ($params->omit() as $param) { // omit nothing to get all params
                    // even when $cachedUserQuery[$param->name] is null
                    // it will still pass as a reference to the array item
                    // that is null at this point, but will be later updated by ref
                    $postQuery = self::applyQueryParamsOnQuery(
                        $postQuery,
                        $param,
                        $cachedUserQuery[$param->name],
                    );
                }
                return $postQuery->selectCurrentAndParentPostID();
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
     * Apply conditions of query params on a query builder that created from posts model
     */
    private static function applyQueryParamsOnQuery(
        Builder $query,
        Param $param,
        ?Collection &$outCachedUserQueryResult,
    ): Builder {
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
            'replySubReplyCount' => 'subReplyCount',
        ][$name] ?? $name;
        $inverseRangeOfNumericParams = [
            '<' => '>=',
            '=' => '!=',
            '>' => '<=',
        ][$sub['range'] ?? null] ?? null;

        $userTypeOfUserParams = str_starts_with($name, 'author') ? 'author' : 'latestReplier';
        $fieldNameOfUserNameParams = str_ends_with($name, 'DisplayName') ? 'displayName' : 'name';
        $getAndCacheUserQuery =
            static function (BuilderContract $newQueryWhenCacheMiss) use (&$outCachedUserQueryResult): Collection {
                // $outCachedUserQueryResult === null means it's the first call
                $outCachedUserQueryResult ??= $newQueryWhenCacheMiss->get();
                return $outCachedUserQueryResult;
            };

        return match ($name) {
            // numeric
            'tid', 'pid', 'spid',
            'authorUid', 'authorExpGrade', 'latestReplierUid',
            'threadViewCount', 'threadShareCount', 'threadReplyCount', 'replySubReplyCount' =>
                $sub['range'] === 'IN' || $sub['range'] === 'BETWEEN'
                    ? $query->{"where$not{$sub['range']}"}($fieldNameOfNumericParams, explode(',', $value))
                    : $query->where(
                        $fieldNameOfNumericParams,
                        $sub['not'] ? $inverseRangeOfNumericParams : $sub['range'],
                        $value,
                    ),
            // textMatch
            'threadTitle', 'postContent' =>
                self::applyTextMatchParamOnQuery($query, $name === 'threadTitle' ? 'title' : 'content', $value, $sub),
            // dateTimeRange
            'postedAt', 'latestReplyPostedAt' =>
                $query->{"where{$not}Between"}($name, explode(',', $value)),
            // array
            'threadProperties' => static function () use ($sub, $inverseNot, $value, $query) {
                foreach ($value as $threadProperty) {
                    match ($threadProperty) {
                        'good' => $query->where('isGood', !$sub['not']),
                        'sticky' => $query->{"where{$inverseNot}null"}('stickyType'),
                    };
                }
                return $query;
            },
            'authorName', 'latestReplierName', 'authorDisplayName', 'latestReplierDisplayName' =>
                $query->{"where{$not}In"}(
                    "{$userTypeOfUserParams}Uid",
                    $getAndCacheUserQuery(self::applyTextMatchParamOnQuery(
                        User::select('uid'),
                        $fieldNameOfUserNameParams,
                        $value,
                        $sub,
                    ))
                ),
            'authorGender', 'latestReplierGender' =>
                $query->{"where{$not}In"}(
                    "{$userTypeOfUserParams}Uid",
                    $getAndCacheUserQuery(User::select('uid')->where('gender', $value))
                ),
            'authorManagerType' =>
                $value === 'NULL'
                    ? $query->{"where{$not}null"}('authorManagerType')
                    : $query->where('authorManagerType', $sub['not'] ? '!=' : '=', $value),
            default => $query,
        };
    }

    /** @psalm-param array<string, mixed> $subParams */
    private static function applyTextMatchParamOnQuery(
        BuilderContract $query,
        string $field,
        string $value,
        array $subParams,
    ): BuilderContract {
        $not = $subParams['not'] === true ? 'Not' : '';
        if ($subParams['matchBy'] === 'regex') {
            return $query->where($field, "$not REGEXP", $value);
        }
        return $query->where(static function (Builder $subQuery) use ($subParams, $field, $not, $value) {
            // not (A or B) <=> not A and not B, following https://en.wikipedia.org/wiki/De_Morgan%27s_laws
            $isOrWhere = $not === 'Not' ? '' : 'or';
            $addMatchKeyword = static fn(string $keyword) =>
                $subQuery->{"{$isOrWhere}Where"}(
                    $field,
                    trim("$not LIKE"),
                    $subParams['matchBy'] === 'implicit' ? "%$keyword%" : $keyword
                );
            // split multiple search keyword by space char when $subParams['spaceSplit'] == true
            foreach ($subParams['spaceSplit'] ? explode(' ', $value) : [$value] as $keyword) {
                $addMatchKeyword($keyword);
            }
        });
    }
}
