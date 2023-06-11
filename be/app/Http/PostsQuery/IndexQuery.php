<?php

namespace App\Http\PostsQuery;

use App\Helper;
use App\Tieba\Eloquent\ForumModel;
use App\Tieba\Eloquent\PostModel;
use App\Tieba\Eloquent\PostModelFactory;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IndexQuery extends BaseQuery
{
    public function query(QueryParams $params, ?string $cursor): self
    {
        /** @var array<string, mixed> $flatParams key by param name */
        $flatParams = array_reduce(
            $params->pick(...ParamsValidator::UNIQUE_PARAMS_NAME, ...Helper::POST_ID),
            static fn (array $accParams, Param $param) =>
                [...$accParams, $param->name => $param->value, ...$param->getAllSub()],
            []
        ); // flatten unique query params
        /** @var Collection<string, int> $postIDParam key by post ID name, should contains only one param */
        $postIDParam = collect($flatParams)->only(Helper::POST_ID);
        /** @var ?string $postIDParamName */
        $postIDParamName = $postIDParam->keys()->first();
        /** @var ?int $postIDParamValue */
        $postIDParamValue = $postIDParam->first();
        $hasPostIDParam = $postIDParam->count() === 1;
        /** @var array<string> $postTypes */
        $postTypes = $flatParams['postTypes'];

        /**
         * @param int $fid
         * @return Collection<string, PostModel> key by post type
         */
        $getQueryBuilders = static fn (int $fid): Collection =>
            collect(PostModelFactory::getPostModelsByFid($fid))
                ->only($postTypes)
                ->transform(static fn (PostModel $model, string $type) => $model->selectCurrentAndParentPostID());
        $getFidByPostIDParam = static function (string $postIDName, int $postID): int {
            $fids = ForumModel::get('fid')->pluck('fid')->toArray();
            $counts = collect($fids)
                ->map(static fn (int $fid) =>
                    DB::table("tbmc_f{$fid}_" . (Helper::POST_ID_TO_TYPE[$postIDName]))
                        ->selectRaw("$fid AS fid, COUNT(*) AS count")
                        ->where($postIDName, $postID))
                ->reduce(static fn (?BuilderContract $acc, EloquentBuilder|QueryBuilder $cur): BuilderContract =>
                    $acc === null ? $cur : $acc->union($cur))
                ->get()->where('count', '!=', 0);
            Helper::abortAPIIf(50001, $counts->count() > 1);
            Helper::abortAPIIf(40401, $counts->count() === 0);
            return $counts->pluck('fid')->first();
        };

        if (\array_key_exists('fid', $flatParams)) {
            /** @var int $fid */ $fid = $flatParams['fid'];
            if ((new ForumModel())->fid($fid)->exists()) {
                /** @var Collection<string, EloquentBuilder<PostModel>> $queries key by post type */
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
                ->only(array_slice(
                    Helper::POST_TYPES, // only query post types that own the querying post ID param
                    array_search($postIDParamName, Helper::POST_ID, true)
                ))
                ->map(static fn (EloquentBuilder $qb, string $type) => $qb->where($postIDParamName, $postIDParamValue));
        }

        if (array_diff($postTypes, Helper::POST_TYPES) !== []) {
            $queries = $queries->only($postTypes);
        }

        if ($flatParams['orderBy'] === 'default') {
            $this->orderByField = 'postTime'; // order by postTime to prevent posts out of order when order by post ID
            if (\array_key_exists('fid', $flatParams) && $postIDParam->count() === 0) { // query by fid only
                $this->orderByDesc = true;
            } elseif ($hasPostIDParam) { // query by post ID (with or without fid)
                $this->orderByDesc = false;
            }
        }

        $this->setResult($fid, $queries, $cursor, $hasPostIDParam ? $postIDParamName : null);
        return $this;
    }
}
