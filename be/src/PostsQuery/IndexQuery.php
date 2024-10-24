<?php

namespace App\PostsQuery;

use App\Repository\ForumRepository;
use App\Repository\Post\PostRepository;
use App\Repository\Post\PostRepositoryFactory;
use App\Helper;
use Doctrine\ORM\QueryBuilder;
use Illuminate\Support\Collection;

readonly class IndexQuery extends BaseQuery
{
    public function __construct(
        QueryResult $queryResult,
        PostsTree $postsTree,
        private PostRepositoryFactory $postRepositoryFactory,
        private ForumRepository $forumRepository,
    ) {
        parent::__construct($queryResult, $postsTree);
    }

    /** @SuppressWarnings(PHPMD.ElseExpression) */
    public function query(QueryParams $params, ?string $cursor): void
    {
        /** @var array<string, mixed> $flatParams key by param name */
        $flatParams = array_reduce(
            $params->pick(...ParamsValidator::UNIQUE_PARAMS_NAME, ...Helper::POST_ID),
            static fn(array $accParams, QueryParam $param) =>
                [...$accParams, $param->name => $param->value, ...$param->getAllSub()],
            [],
        ); // flatten unique query params
        /** @var Collection<string, int> $postIDParam key by post ID name, should contains only one param */
        $postIDParam = collect($flatParams)->only(Helper::POST_ID);
        $postIDParamName = $postIDParam->keys()->first();
        $postIDParamValue = $postIDParam->first();
        $hasPostIDParam = $postIDParam->count() === 1;
        /** @var array<string> $postTypes */
        $postTypes = $flatParams['postTypes'];

        if ($flatParams['orderBy'] === 'default') {
            $this->setOrderByField('postedAt'); // order by postedAt to prevent posts out of order when order by post ID
            if (\array_key_exists('fid', $flatParams) && $postIDParam->count() === 0) { // query by fid only
                $this->setOrderByDesc(true);
            } elseif ($hasPostIDParam) { // query by post ID (with or without fid)
                $this->setOrderByDesc(false);
            }
        }

        /**
         * @param int $fid
         * @return Collection<string, PostRepository> key by post type
         */
        $getQueryBuilders = fn(int $fid): Collection =>
            collect($this->postRepositoryFactory->newForumPosts($fid))
                ->only($postTypes)
                ->transform(fn(PostRepository $repository) => $repository->selectPostKeyDTO($this->orderByField));
        $getFidByPostIDParam = function (string $postIDName, int $postID): int {
            $postExistencesKeyByFid = collect($this->forumRepository->getOrderedForumsId())
                ->mapWithKeys(fn(int $fid) => [$fid => $this->postRepositoryFactory
                    ->new($fid, Helper::POST_ID_TO_TYPE[$postIDName])
                    ->isPostExists($postID)])
                ->filter(fn(bool $isExists) => $isExists);
            Helper::abortAPIIf(50001, $postExistencesKeyByFid->count() > 1);
            Helper::abortAPIIf(40401, $postExistencesKeyByFid->count() === 0);
            return $postExistencesKeyByFid->keys()[0];
        };

        if (\array_key_exists('fid', $flatParams)) {
            /** @var int $fid */ $fid = $flatParams['fid'];
            if ($this->forumRepository->isForumExists($fid)) {
                /** @var Collection<string, QueryBuilder> $queries key by post type */
                $queries = $getQueryBuilders($fid);
            } elseif ($hasPostIDParam) { // query by post ID and fid, but the provided fid is invalid
                $fid = $getFidByPostIDParam($postIDParamName, $postIDParamValue);
                $queries = $getQueryBuilders($fid);
            } else {
                Helper::abortAPI(40406);
            }
        } elseif ($hasPostIDParam) { // query by post ID only
            $fid = $getFidByPostIDParam($postIDParamName, $postIDParamValue);
            $queries = $getQueryBuilders($fid);
        } else {
            Helper::abortAPI(40001);
        }

        if ($hasPostIDParam) {
            $queries = $queries
                ->only(\array_slice(
                    Helper::POST_TYPES, // only query post types that own the querying post ID param
                    array_search($postIDParamName, Helper::POST_ID, true),
                ))
                ->each(static fn(QueryBuilder $qb, string $type) =>
                    $qb->where("t.$postIDParamName = :postIDParamValue")
                        ->setParameter('postIDParamValue', $postIDParamValue));
        }
        if (array_diff($postTypes, Helper::POST_TYPES) !== []) {
            $queries = $queries->only($postTypes);
        }

        $this->queryResult->setResult(
            $fid,
            $queries,
            $cursor,
            $this->orderByField,
            $this->orderByDesc,
            queryByPostIDParamName: $hasPostIDParam ? $postIDParamName : null,
        );
    }
}
