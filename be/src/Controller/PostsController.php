<?php

namespace App\Controller;

use App\DTO\User\AuthorExpGrade;
use App\DTO\User\ForumModerator;
use App\DTO\User\User;
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
            ->setParams(\Safe\json_decode($request->query->get('query'), true))
            ->getParams();
        $this->paramsValidator->addDefaultParamsThenValidate();

        $this->stopwatch->start('$queryClass->query()');
        $this->query->query($params, $request->query->get('cursor'));
        $this->stopwatch->stop('$queryClass->query()');

        $this->stopwatch->start('fillWithParentPost');
        $matchQueryPostCounts = $this->query->postsTree->fillWithParentPost($this->query->queryResult);
        $this->stopwatch->stop('fillWithParentPost');

        $this->stopwatch->start('queryUsers');
        $latestRepliers = $this->latestReplierRepository->getLatestRepliersWithoutNameWhenHasUid(
            $this->query->postsTree->threads->map(fn(Thread $thread) => $thread->getLatestReplierId()),
        );
        $uids = collect([$this->query->postsTree->threads, $this->query->postsTree->replies, $this->query->postsTree->subReplies])
            ->flatMap(static fn(Collection $posts) =>
                $posts->map(fn(Post $post) => $post->getAuthorUid()))
            ->concat($latestRepliers->pluck('uid')
                ->filter(static fn(?int $uid) => $uid !== null))
            ->unique();
        $users = collect($this->userRepository->getUsers($uids))
            ->map(fn(\App\Entity\User $entity) => User::fromEntity($entity));
        $this->stopwatch->stop('queryUsers');

        $this->stopwatch->start('queryUserRelated');
        $fid = $this->query->queryResult->fid;
        $authorExpGrades = collect($this->authorExpGradeRepository->getLatestOfUsers($fid, $uids))
            ->keyBy(fn(AuthorExpGrade $authorExpGrade) => $authorExpGrade->uid);
        $users->each(fn(User $user) => $user->setCurrentAuthorExpGrade($authorExpGrades[$user->getUid()] ?? null));

        $forumModerators = collect($this->forumModeratorRepository
                ->getLatestOfUsers($fid, $users->map(fn(User $user) => $user->getPortrait())))
            ->keyBy(fn(ForumModerator $forumModerator) => $forumModerator->portrait);
        $users->each(fn(User $user) => $user->setCurrentForumModerator($forumModerators->get($user->getPortrait())));
        $this->stopwatch->stop('queryUserRelated');

        return [
            'pages' => [
                'currentCursor' => $this->query->queryResult->currentCursor,
                'nextCursor' => $this->query->queryResult->nextCursor,
                ...$matchQueryPostCounts,
            ],
            'forum' => $this->forumRepository->getForum($fid),
            'threads' => $this->query->postsTree->reOrderNestedPosts(
                $this->query->postsTree->nestPostsWithParent(),
                $this->query->getOrderByField(),
                $this->query->isOrderByDesc(),
            ),
            'users' => $users,
            'latestRepliers' => $latestRepliers,
            'query' => $this->query->queryResult->query,
        ];
    }
}
