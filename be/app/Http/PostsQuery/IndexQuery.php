<?php

namespace App\Http\PostsQuery;

use App\Helper;
use App\Tieba\Eloquent\ForumModel;
use App\Tieba\Eloquent\PostModel;
use App\Tieba\Eloquent\PostModelFactory;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IndexQuery
{
    use BaseQuery;

    public function query(QueryParams $params): self
    {
        /** @var array<string, mixed> $flatParams keyed by param name */
        $flatParams = array_reduce(
            $params->pick(...ParamsValidator::UNIQUE_PARAMS_NAME, ...Helper::POST_ID),
            static fn (array $accParams, Param $param) =>
                [...$accParams, $param->name => $param->value, ...$param->getAllSub()],
            []
        ); // flatten unique query params
        /** @var Collection<string, int> $postIDParam keyed by post ID name, should contains only one param */
        $postIDParam = collect($flatParams)->only(Helper::POST_ID);
        /** @var string $postIDParamName */
        $postIDParamName = $postIDParam->keys()->first();
        /** @var int $postIDParamValue */
        $postIDParamValue = $postIDParam->first();
        $hasPostIDParam = $postIDParam->count() === 1;
        /** @var array<string> $postTypes */
        $postTypes = $flatParams['postTypes'];

        /**
         * @param int $fid
         * @return Collection<string, Builder> keyed by post type
         */
        $getQueryBuilders = static fn (int $fid): Collection =>
            collect(PostModelFactory::getPostModelsByFid($fid))->only($postTypes)
                ->transform(static fn (PostModel $model, string $type) => $model
                    // latter we can do Collection::groupBy(type)
                    ->selectRaw('"' . Helper::POST_TYPE_TO_PLURAL[$type] . '" AS typePluralName')
                    ->selectCurrentAndParentPostID());
        /**
         * @param array<string, int> $postsID keyed by post ID name
         * @return int fid
         */
        $getFidByPostIDParam = static function (string $postIDName, int $postID): int {
            $fids = ForumModel::get('fid')->pluck('fid')->toArray();
            $counts = collect($fids)
                ->map(static fn (int $fid) =>
                    DB::table("tbm_f{$fid}_" . (Helper::POST_ID_TO_TYPE_PLURAL[$postIDName]))
                        ->selectRaw("$fid AS fid, COUNT(*) AS count")
                        ->where($postIDName, $postID))
                ->reduce(static fn (?BuilderContract $acc, Builder|\Illuminate\Database\Query\Builder $cur): BuilderContract =>
                    $acc === null ? $cur : $acc->union($cur))
                ->get()->where('count', '!=', 0);
            Helper::abortAPIIf(50001, $counts->count() > 1);
            Helper::abortAPIIf(40401, $counts->count() == 0);
            return $counts->pluck('fid')->first();
        };

        if (\array_key_exists('fid', $flatParams)) {
            /** @var int $fid */ $fid = $flatParams['fid'];
            if ((new ForumModel())->fid($fid)->exists()) {
                /** @var Collection<string, Builder> $queries keyed by post type */
                $queries = $getQueryBuilders($fid);
            } elseif ($hasPostIDParam) { // query by post ID and fid, but the provided fid is invalid
                $fid = $getFidByPostIDParam($postIDParamName, $postIDParamValue);
                $queries = $getQueryBuilders($fid);
            } else {
                Helper::abortAPI(40006);
            }
        } elseif ($hasPostIDParam) { // query by post ID only
            $fid = $getFidByPostIDParam($postIDParamName, $postIDParamValue);
            $queries = $getQueryBuilders($fid);
        } else {
            Helper::abortAPI(40001);
        }

        if ($hasPostIDParam) {
            $queries = $queries
                ->only(\array_slice(Helper::POST_TYPES, // only query post types that own the querying post ID param
                    array_search($postIDParamName, Helper::POST_ID, true)))
                ->map(static fn (Builder $qb, string $type) => $qb->where($postIDParamName, $postIDParamValue));
        }

        if (array_diff($postTypes, Helper::POST_TYPES) !== []) {
            $queries = $queries->only($postTypes);
        }

        /** @var string $orderByParam */
        $orderByParam = $flatParams['orderBy'];
        if ($orderByParam !== 'default') {
            /**
             * @param Builder $qb
             * @return Builder
             */
            $queryOrderByTranformer = static fn (Builder $qb) =>
                $qb->addSelect($orderByParam)->orderBy($orderByParam, $flatParams['direction']);
            /** @var array{callback: callable(PostModel): mixed, descending: bool} $resultSortBySelector */
            $resultSortBySelector = [
                'callback' => static fn (PostModel $i) => $i->{$orderByParam},
                'descending' => $flatParams['direction'] === 'DESC'
            ];
        } elseif (\array_key_exists('fid', $flatParams) && $postIDParam->count() === 0) { // query by fid only
            // order by postTime to prevent posts out of order when order by post ID
            $queryOrderByTranformer = static fn (Builder $qb) =>
                $qb->addSelect('postTime')->orderByDesc('postTime');
            $resultSortBySelector = [
                'callback' => static fn (PostModel $i) => $i->postTime,
                'descending' => true
            ];
        } elseif ($hasPostIDParam) { // query by post ID (with or without fid)
            $queryOrderByTranformer = static fn (Builder $qb) =>
                $qb->addSelect('postTime')->orderBy('postTime', 'ASC');
            $resultSortBySelector = [
                'callback' => static fn (PostModel $i) => $i->postTime,
                'descending' => false
            ];
        } else {
            Helper::abortAPI(40004);
        }

        /** @var Collection<string, CursorPaginator> $paginators keyed by post type */
        $paginators = $queries->map($queryOrderByTranformer)
            ->map(fn (Builder $qb) => $qb->cursorPaginate($this->perPageItems));
        $this->setResult($fid, $paginators, $paginators
            ->flatMap(static fn(CursorPaginator $paginator) => $paginator->collect()) // cast queried posts to collection for each post type, then flatten all types of posts
            ->sortBy(...$resultSortBySelector) // sort by the required sorting field and direction
            ->take($this->perPageItems) // LIMIT $perPageItems
            ->groupBy('typePluralName')); // gather limited posts by their type

        return $this;
    }
}
