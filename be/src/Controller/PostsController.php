<?php

namespace App\Controller;

use App\DTO\User\AuthorExpGrade;
use App\DTO\User\ForumModerator;
use App\DTO\User\User;
use App\Entity\LatestReplier;
use App\Entity\Post\Post;
use App\Entity\Post\Thread;
use App\PostsQuery\ParamsValidator;
use App\PostsQuery\Query;
use App\Repository\ForumRepository;
use App\Repository\LatestReplierRepository;
use App\Repository\Revision\AuthorExpGradeRepository;
use App\Repository\Revision\ForumModeratorRepository;
use App\Repository\UserRepository;
use App\Validator\Validator;
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
        private readonly ForumModeratorRepository $forumModeratorRepository,
        private readonly AuthorExpGradeRepository $authorExpGradeRepository,
        private readonly ParamsValidator $paramsValidator,
        private readonly Query $query,
    ) {}

    #[Route('/api/posts')]
    public function query(Request $request): array
    {
        $this->validator->validate($request->query->all(), new Assert\Collection([
            'cursor' => new Assert\Optional(new Assert\Regex(
                // https://stackoverflow.com/questions/475074/regex-to-parse-or-validate-base64-data
                // (,|$)|,){5,6} means allow at most 5~6 parts of base64 segment or empty string to exist
                /** @lang RegExp */'/^(([A-Za-z0-9-_]{4})*([A-Za-z0-9-_]{2,3})(,|$)|,){5,6}$/',
            )),
            'query' => new Assert\Required(new Assert\Json()),
        ]));

        $params = $this->paramsValidator
            ->setParams(\Safe\json_decode($request->query->get('query'), associative: true))
            ->getParams();
        $this->paramsValidator->addDefaultParamsThenValidate();

        $this->stopwatch->start('$queryClass->query()');
        $this->query->query($params, $request->query->get('cursor'));
        $this->stopwatch->stop('$queryClass->query()');

        $this->stopwatch->start('fillWithParentPost');
        $matchQueryPostCounts = $this->query->postsTree->fillWithParentPost($this->query->queryResult);
        $this->stopwatch->stop('fillWithParentPost');

        $this->stopwatch->start('queryUsers');
        $latestRepliersIdKeyByFid = $this->query->postsTree->threads
            ->map(fn(Thread $thread) => ['fid' => $thread->fid, 'latestReplierId' => $thread->latestReplierId])
            ->filter(fn(array $fidAndLatestReplierId) => $fidAndLatestReplierId['latestReplierId'] !== null)
            ->groupBy(fn(array $fidAndLatestReplierId) => $fidAndLatestReplierId['fid'])
            ->map(fn(Collection $fidAndLatestRepliersUid) => $fidAndLatestRepliersUid->pluck('latestReplierId'));
        $latestRepliers = $this->latestReplierRepository->getLatestRepliersWithoutNameWhenHasUid($latestRepliersIdKeyByFid->flatten());
        $posts = collect([
            $this->query->postsTree->threads,
            $this->query->postsTree->replies,
            $this->query->postsTree->subReplies
        ])->flatten();
        $latestRepliersUidKeyById = collect($latestRepliers)
            ->mapWithKeys(fn(array|LatestReplier $latestReplier) => [
                is_array($latestReplier) ? $latestReplier['id'] : $latestReplier->getId() =>
                    is_array($latestReplier) ? $latestReplier['uid'] : $latestReplier->getUid()
            ])
            ->filter(static fn(?int $uid) => $uid !== null);
        $uids = $posts
            ->map(fn(Post $post) => $post->authorUid)
            ->concat($latestRepliersUidKeyById)
            ->unique();
        $users = collect($this->userRepository->getUsers($uids))
            ->mapWithKeys(fn(\App\Entity\User $entity) => [$entity->getUid() => User::fromEntity($entity)]);
        $this->stopwatch->stop('queryUsers');

        $this->stopwatch->start('queryUserRelated');
        $authorsUidKeyByFid = $posts
            ->map(fn(Post $post) => ['fid' => $post->fid, 'authorUid' => $post->authorUid])
            ->groupBy(fn(array $fidAndAuthorId) => $fidAndAuthorId['fid'])
            ->map(fn(Collection $fidAndAuthorsUid) => $fidAndAuthorsUid->pluck('authorUid'));
        $authorExpGrades = collect($this->authorExpGradeRepository->getLatestOfUsers($authorsUidKeyByFid))
            ->keyBy(fn(AuthorExpGrade $authorExpGrade) => $authorExpGrade->uid);

        /** @var Collection<int, int> $intersectedFidInUsersId */
        /** @var Collection<int, int> $uniqueFidInAuthorsUid */
        [$intersectedFidInUsersId, $uniqueFidInAuthorsUid] = $authorsUidKeyByFid->keys()
            ->partition(fn(int $fid) => $latestRepliersIdKeyByFid->keys()->contains($fid));
        $usersIdKeyByFid = $intersectedFidInUsersId
            ->mapWithKeys(fn(int $fid) => [$fid =>
                $latestRepliersIdKeyByFid[$fid]
                    ->map(fn(int $latestReplierId) => $latestRepliersUidKeyById->get($latestReplierId))
                    ->filter(fn(?int $latestReplierUid) => $latestReplierUid !== null)
                    ->merge($authorsUidKeyByFid[$fid])
                    ->unique()
                    ->values()])
            ->replace($authorsUidKeyByFid->only($uniqueFidInAuthorsUid));
        $forumModerators = collect($this->forumModeratorRepository->getLatestOfUsers($usersIdKeyByFid
            ->map(fn(Collection $usersId) => $usersId
                ->map(fn(int $uid) => $users->get($uid)?->getPortrait())
                ->filter(fn(?string $portrait) => $portrait !== null))
        ))->keyBy(fn(ForumModerator $forumModerator) => $forumModerator->portrait);

        $users = $users->each(fn(User $user) => $user->setForumSpecific([
            'authorExpGrade' => $authorExpGrades->get($user->getUid()),
            'forumModerator' => $forumModerators->get($user->getPortrait())
        ]));
        $this->stopwatch->stop('queryUserRelated');

        return [
            'pages' => [
                'currentCursor' => $this->query->queryResult->currentCursor,
                'nextCursor' => $this->query->queryResult->nextCursor,
                ...$matchQueryPostCounts,
            ],
            'forums' => collect($this->forumRepository
                ->getForums($posts->map(fn(Post $post) => $post->fid)->unique())
            )->mapWithKeys(fn(array $forum) => [$forum['fid'] => $forum['name']]),
            'threads' => $this->query->postsTree->reOrderNestedPosts(
                $this->query->postsTree->nestPostsWithParent(),
                $this->query->getOrderByField(),
                $this->query->isOrderByDesc(),
            ),
            'users' => $users->values(),
            'latestRepliers' => $latestRepliers,
            'query' => $this->query->queryResult->query,
        ];
    }
}
