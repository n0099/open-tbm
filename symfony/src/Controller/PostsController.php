<?php

namespace App\Controller;

use App\Helper;
use App\PostsQuery\IndexQuery;
use App\PostsQuery\ParamsValidator;
use App\PostsQuery\SearchQuery;
use App\Repository\ForumRepository;
use App\Repository\LatestReplierRepository;
use App\Repository\UserRepository;
use App\Validator\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Validator\Constraints as Assert;

class PostsController extends AbstractController
{
    public function __construct(
        private readonly Stopwatch $stopwatch,
        private readonly Validator $validator,
        private readonly ForumRepository $forumRepository,
        private readonly UserRepository $userRepository,
        private readonly LatestReplierRepository $latestReplierRepository,
    ) {}

    #[Route('/posts')]
    public function query(Request $request): array
    {
        $this->validator->validate($request->query->all(), new Assert\Collection([
            'cursor' => new Assert\Optional(new Assert\Regex( // https://stackoverflow.com/questions/475074/regex-to-parse-or-validate-base64-data
                // (,|$)|,){5,6} means allow at most 5~6 parts of base64 segment or empty string to exist
                '/^(([A-Za-z0-9-_]{4})*([A-Za-z0-9-_]{2,3})(,|$)|,){5,6}$/'
            )),
            'query' => new Assert\Required(new Assert\Json()),
        ]));
        $validator = new ParamsValidator($this->validator, \Safe\json_decode($request->query->get('query'), true));
        $params = $validator->params;

        $postIDParams = $params->pick(...Helper::POST_ID);
        $isQueryByPostID =
            // is there no other params except unique params and post ID params
            \count($params->omit(...ParamsValidator::UNIQUE_PARAMS_NAME, ...Helper::POST_ID)) === 0
            // is there only one post ID param
            && \count($postIDParams) === 1
            // is all post ID params doesn't own any sub param
            && array_filter($postIDParams, static fn($p) => $p->getAllSub() !== []) === [];
        $isFidParamNull = $params->getUniqueParamValue('fid') === null;
        // is the fid param exists and there's no other params except unique params
        $isQueryByFid = !$isFidParamNull && \count($params->omit(...ParamsValidator::UNIQUE_PARAMS_NAME)) === 0;
        $isIndexQuery = $isQueryByPostID || $isQueryByFid;
        $isSearchQuery = !$isIndexQuery;
        Helper::abortAPIIf(40002, $isSearchQuery && $isFidParamNull);

        $validator->addDefaultParamsThenValidate(shouldSkip40003: $isIndexQuery);

        $queryClass = $isIndexQuery ? IndexQuery::class : SearchQuery::class;
        $this->stopwatch->start('$queryClass->query()');
        $query = (new $queryClass())->query($params, $this->getParameter('cursor'));
        $this->stopwatch->stop('$queryClass->query()');
        $this->stopwatch->start('fillWithParentPost');
        $result = $query->fillWithParentPost();
        $this->stopwatch->stop('fillWithParentPost');

        $this->stopwatch->start('queryUsers');
        $latestRepliersId = $result['threads']->pluck('latestReplierId');
        $latestRepliers = collect()
            ->concat($this->latestReplierRepository->createQueryBuilder('t')
                ->where('t.id IN (?ids)')->setParameter('ids', $latestRepliersId)
                ->where('t.uid IS NOT NULL')
                ->getQuery()->getResult())
            ->concat($this->latestReplierRepository->createQueryBuilder('t')
                ->where('t.id IN (?ids)')->setParameter('ids', $latestRepliersId)
                ->where('t.uid IS NULL')
                ->addSelect('name', 'displayName')
                ->getQuery()->getResult());
        $users = $this->userRepository->createQueryBuilder('t')
            ->where('t.uid IN (:uids)')
            ->setParameter(
                'uids',
                collect($result)
                    ->only(Helper::POST_TYPES_PLURAL)
                    ->flatMap(static fn(Collection $posts) => $posts->pluck('authorUid'))
                    ->concat($latestRepliers->pluck('uid'))
                    ->filter()->unique()->toArray() // remove NULLs
            )
            ->getQuery()->getResult();
        $this->stopwatch->stop('queryUsers');

        return [
            'type' => $isIndexQuery ? 'index' : 'search',
            'pages' => [
                ...$query->getResultPages(),
                ...Arr::except($result, ['fid', ...Helper::POST_TYPES_PLURAL]),
            ],
            'forum' => $this->forumRepository->createQueryBuilder('t')
                ->where('t.fid = :fid')->setParameter('fid', $result['fid'])
                ->setMaxResults(1)->getQuery()->getResult(),
            'threads' => $query->reOrderNestedPosts($query->nestPostsWithParent(...$result)),
            'users' => $users,
            'latestRepliers' => $latestRepliers,
        ];
    }
}
