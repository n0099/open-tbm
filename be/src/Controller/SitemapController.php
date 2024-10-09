<?php

namespace App\Controller;

use App\Helper;
use App\Repository\ForumRepository;
use App\Repository\Post\PostRepositoryFactory;
use App\Validator\Validator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SitemapController extends AbstractController
{
    public static int $maxUrls = 50000;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ForumRepository $forumRepository,
        private readonly PostRepositoryFactory $postRepositoryFactory
    ) {}

    #[Route('/sitemaps/forums')]
    public function forums(): Response
    {
        $threadsIdKeyByFid = collect($this->forumRepository->getOrderedForumsId())
            ->mapWithKeys(fn(int $fid) => [
                $fid => $this->postRepositoryFactory->newThread($fid)->getThreadsIdByChunks(self::$maxUrls),
            ])
            ->toArray();

        return $this->cache->get(
            '/sitemaps/forums',
            function (ItemInterface $item) use ($threadsIdKeyByFid) {
                $item->expiresAfter(86400);
                return $this->renderXml(
                    'sitemaps/forums.xml.twig',
                    ['threads_id_key_by_fid' => $threadsIdKeyByFid],
                );
            });
    }

    #[Route('/sitemaps/forums/{fid}/threads', requirements: ['fid' => /** @lang JSRegexp */'\d+'])]
    public function threads(Request $request, Validator $validator, int $fid): Response
    {
        $cursor = $request->query->get('cursor') ?? 0;
        $validator->validate($cursor, new Assert\Type('digit'));
        Helper::abortAPIIfNot(40406, $this->forumRepository->isForumExists($fid));

        return $this->cache->get(
            "/sitemaps/forums/$fid/threads?cursor=$cursor",
            function (ItemInterface $item) use ($fid, $cursor) {
                $item->expiresAfter(86400);
                return $this->renderXml(
                    'sitemaps/threads.xml.twig',
                    [
                        'threads_id' =>
                            $this->postRepositoryFactory->newThread($fid)->getThreadsIdAfter($cursor, self::$maxUrls),
                        'base_url_fe' => $this->getParameter('app.base_url.fe')
                    ]
                );
            });
    }

    private function renderXml(string $view, array $parameters): Response
    {
        return $this->render($view, $parameters, new Response(headers: ['Content-Type' => 'text/xml']));
    }
}
