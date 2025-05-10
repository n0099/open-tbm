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

        /** @var Collection<string, QueryBuilder> $queries key by post type */
        $queries = collect($this->postRepositoryFactory->newForumPosts())
            ->only($postTypes)
            ->transform(fn(PostRepository $repository) => $repository
                ->selectPostKeyDTO($this->orderByField));

        Helper::abortAPIIf(40406, array_key_exists('fid', $flatParams)
            && !$this->forumRepository->isForumExists($flatParams['fid']));

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

        $this->queryResult->setResult(
            $queries,
            $cursor,
            $this->orderByField,
            $this->orderByDesc,
            queryByPostIDParamName: $hasPostIDParam ? $postIDParamName : null,
        );
    }
}
