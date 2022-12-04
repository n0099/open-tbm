<?php

namespace App\Http\PostsQuery;

use App\Helper;
use App\Tieba\Eloquent\IndexModel;
use Illuminate\Support\Arr;

class IndexQuery
{
    use BaseQuery;

    public function query(QueryParams $params): self
    {
        $flatParams = array_reduce(
            $params->pick(...ParamsValidator::UNIQUE_PARAMS_NAME, ...Helper::POSTS_ID),
            static fn (array $accParams, Param $param) =>
                [...$accParams, $param->name => $param->value, ...$param->getAllSub()],
            []
        ); // flatten unique query params

        $query = IndexModel::where(Arr::only($flatParams, ['fid', ...Helper::POSTS_ID]));
        if ($flatParams['orderBy'] !== 'default') {
            $query->orderBy($flatParams['orderBy'], $flatParams['direction']);
        } elseif (Arr::only($flatParams, Helper::POSTS_ID) === []) { // query by fid only
            $query->orderByDesc('postTime'); // order by postTime to prevent posts out of order when order by post id
        } else { // query by post id
            // order by all posts id to keep reply and sub reply continuous instated of clip into multi-page since they are varied in postTime
            $query->orderByMulti(array_fill_keys(Helper::POSTS_ID, 'ASC'));
        }
        if ($flatParams['postTypes'] !== Arr::sort(Helper::POST_TYPES)) {
            $query->whereIn('type', $flatParams['postTypes']);
        }
        $result = $query->simplePaginate($this->perPageItems);
        Helper::abortAPIIf(40401, $result->isEmpty());

        $this->queryResult = [
            'fid' => $result->pluck('fid')->first(),
            ...array_combine(Helper::POST_TYPES_PLURAL, array_map( // assign queried posts id from $query
                static fn ($postType) => $result->where('type', $postType)->toArray(),
                Helper::POST_TYPES
            ))
        ];
        $this->queryResultPages = [
            'firstItem' => $result->firstItem(),
            'itemCount' => $result->count(),
            'currentPage' => $result->currentPage()
        ];

        return $this;
    }
}
