<?php

namespace App\Http\PostsQuery;

use App\Helper;
use App\Tieba\Eloquent\ForumModel;
use App\Tieba\Eloquent\PostModel;
use App\Tieba\Eloquent\PostModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

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
        $postIDParams = Arr::only($flatParams, Helper::POSTS_ID);
        $postTypes = $flatParams['postTypes'];

        $getBuilders = function (int $fid) use ($postTypes, $postIDParams): Collection {
            $postModelBuilders = collect(PostModelFactory::getPostModelsByFid($fid))->only($postTypes)
                ->transform(static fn (PostModel $model, string $type) =>
                $model->selectRaw('"' . Helper::POST_TYPES_TO_PLURAL[$type] . '" AS type') // latter we can do Collection::groupBy(type)
                ->addSelect(Helper::POSTS_TYPE_ID[$type])); // only fetch posts id when we can fetch all fields since BaseQuery::fillWithParentPost() will do the rest
            return count($postIDParams) > 0
                ? collect($postIDParams)->flatMap( // query with both post id and fid, array_merge(...array_map()) is flatmap
                    static fn (int $postID, string $type) => [$type => $postModelBuilders[$type]->where($type, $postID)])
                : $postModelBuilders; // query by fid only
        };
        $getFidByPostsID = function (array $postsID): int {
            // TODO
        };

        if (\array_key_exists('fid', $flatParams)) {
            /** @var int $fid */ $fid = $flatParams['fid'];
            if ((new ForumModel())->fid($fid)->exists()) {
                $queries = $getBuilders($fid);
            } elseif (count($postIDParams) > 0) { // query by post id and fid, but the provided fid is invalid
                $queries = $getBuilders($getFidByPostsID($postIDParams));
            } else {
                Helper::abortAPI(40006);
            }
        } elseif (count($postIDParams) > 0) { // query by post id only
            $queries = $getBuilders($getFidByPostsID($postIDParams));
        } else {
            Helper::abortAPI(40001);
        }

        if (array_diff($postTypes, Helper::POST_TYPES) !== []) {
            $queries = $queries->only($postTypes);
        }

        if ($flatParams['orderBy'] !== 'default') {
            $queryOrderByTranformer = static fn (Builder $qb) =>
                $qb->addSelect($flatParams['orderBy'])->orderBy($flatParams['orderBy'], $flatParams['direction']);
            $resultSortBySelector = [
                'callback' => static fn (PostModel $i) => $i->{$flatParams['orderBy']},
                'descending' => $flatParams['direction'] === 'DESC'
            ];
        } elseif (\array_key_exists('fid', $flatParams) && count($postIDParams) === 0) { // query by fid only
            // order by postTime to prevent posts out of order when order by post id
            $queryOrderByTranformer = static fn (Builder $qb) =>
                $qb->addSelect('postTime')->orderByDesc('postTime');
            $resultSortBySelector = [
                'callback' => static fn (PostModel $i) => $i->postTime,
                'descending' => true
            ];
        } elseif (count($postIDParams) > 0) { // query by post id (with or without fid)
            $queryOrderByTranformer = static fn (Builder $qb) =>
                $qb->addSelect('postTime')->orderBy('postTime', 'ASC');
            $resultSortBySelector = [
                'callback' => static fn (PostModel $i) => $i->postTime,
                'descending' => false
            ];
        } else {
            Helper::abortAPI(40004);
        }

        $paginators = $queries->map($queryOrderByTranformer)->flatMap(
            fn (Builder $qb, string $type) => [$type => $qb->simplePaginate($this->perPageItems)]);
        $this->setResult($fid, $paginators, $paginators
            ->flatMap(static fn(Paginator $result, string $type) => [$type => $result->collect()])
            ->flatten(1)
            ->sortBy(...$resultSortBySelector)
            ->take($this->perPageItems)
            ->groupBy('type'));

        return $this;
    }
}
