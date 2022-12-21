<?php

namespace App\Http\PostsQuery;

use App\Helper;
use App\Tieba\Eloquent\ForumModel;
use App\Tieba\Eloquent\PostModel;
use App\Tieba\Eloquent\PostModelFactory;
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
        /** @var Collection<string, int> $postIDParams keyed by post ID name */
        $postIDParams = collect($flatParams)->only(Helper::POST_ID);
        /** @var array<string> $postTypes */
        $postTypes = $flatParams['postTypes'];

        /**
         * @param int $fid
         * @return Collection<string, Builder> keyed by post type
         */
        $getQueryBuilders = static function (int $fid) use ($postTypes, $postIDParams): Collection {
            $postModelBuilders = collect(PostModelFactory::getPostModelsByFid($fid))->only($postTypes)
                ->transform(static fn (PostModel $model, string $type) => $model
                    // latter we can do Collection::groupBy(type)
                    ->selectRaw('"' . Helper::POST_TYPE_TO_PLURAL[$type] . '" AS typePluralName')
                    ->selectCurrentAndParentPostID());
            return $postIDParams->count() > 0
                ? $postIDParams->mapWithKeys( // query with both post ID and fid
                    static function (int $postID, string $postIDName) use ($postModelBuilders) {
                        $type = Helper::POST_ID_TO_TYPE[$postIDName];
                        return [$type => $postModelBuilders[$type]->where($postIDName, $postID)];
                    })
                : $postModelBuilders; // query by fid only
        };
        /**
         * @param array<string, int> $postsID keyed by post ID name
         * @return int fid
         */
        $getFidByPostsID = static function (Collection $postsID): int {
            $fids = ForumModel::get('fid')->pluck('fid')->toArray();
            $fidKeyByPostIDName = $postsID->map(function (int $id, string $postIDName) use ($fids): int {
                $pluralPostTypeName = Helper::POST_ID_TO_TYPE_PLURAL[$postIDName];
                $counts = collect(DB::select(
                    implode(' UNION ALL ', array_map(static fn (int $fid) =>
                        "(SELECT $fid AS fid, COUNT(*) AS count FROM tbm_f{$fid}_{$pluralPostTypeName} WHERE $postIDName = ?)", $fids)),
                    [$id]
                ))->where('count', '!=', 0);
                Helper::abortAPIIf(50001, $counts->count() > 1);
                Helper::abortAPIIf(40401, $counts->count() == 0);
                return $counts->pluck('fid')->first();
            });
            Helper::abortAPIIf(40007, $fidKeyByPostIDName->unique()->count() > 1);
            /** @noinspection PhpStrictTypeCheckingInspection */
            return $fidKeyByPostIDName->first();
        };

        if (\array_key_exists('fid', $flatParams)) {
            /** @var int $fid */ $fid = $flatParams['fid'];
            if ((new ForumModel())->fid($fid)->exists()) {
                /** @var Collection<string, Builder> $queries keyed by post type */
                $queries = $getQueryBuilders($fid);
            } elseif ($postIDParams->count() > 0) { // query by post ID and fid, but the provided fid is invalid
                $fid = $getFidByPostsID($postIDParams);
                $queries = $getQueryBuilders($fid);
            } else {
                Helper::abortAPI(40006);
            }
        } elseif ($postIDParams->count() > 0) { // query by post ID only
            $fid = $getFidByPostsID($postIDParams);
            $queries = $getQueryBuilders($fid);
        } else {
            Helper::abortAPI(40001);
        }

        if (array_diff($postTypes, Helper::POST_TYPES) !== []) {
            $queries = $queries->only($postTypes);
        }

        if ($flatParams['orderBy'] !== 'default') {
            /**
             * @param Builder $qb
             * @return Builder
             */
            $queryOrderByTranformer = static fn (Builder $qb) =>
                $qb->addSelect($flatParams['orderBy'])->orderBy($flatParams['orderBy'], $flatParams['direction']);
            /** @var array{callback: callable(PostModel): mixed, descending: bool} $resultSortBySelector */
            $resultSortBySelector = [
                'callback' => static fn (PostModel $i) => $i->{$flatParams['orderBy']},
                'descending' => $flatParams['direction'] === 'DESC'
            ];
        } elseif (\array_key_exists('fid', $flatParams) && $postIDParams->count() === 0) { // query by fid only
            // order by postTime to prevent posts out of order when order by post ID
            $queryOrderByTranformer = static fn (Builder $qb) =>
                $qb->addSelect('postTime')->orderByDesc('postTime');
            $resultSortBySelector = [
                'callback' => static fn (PostModel $i) => $i->postTime,
                'descending' => true
            ];
        } elseif ($postIDParams->count() > 0) { // query by post ID (with or without fid)
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
