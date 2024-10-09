<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AssetController extends AbstractController
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly Packages $assets,
    ) {}

    #[Route('/assets/{filename}', requirements: [
        'filename' => /** @lang JSRegexp */'(react(|-dom|-json-view)|scheduler)\.js',
    ])]
    public function getAsset(string $filename): Response
    {
        return new Response(
            content: preg_replace_callback_array([
                '#/npm/(?<filename>\w+)@(\d+\.?){3}/\+esm#' =>
                    fn(array $m) => $this->assets->getUrl("assets/{$m['filename']}.js"),
                '@^//# sourceMappingURL=.+$@m' =>
                    static fn() => '',
            ], $this->filesystem->readFile(
                $this->getParameter('kernel.project_dir') . "/public/react-json-view/$filename",
            )),
            headers: ['Content-Type' => 'text/javascript'],
        );
    }
}
