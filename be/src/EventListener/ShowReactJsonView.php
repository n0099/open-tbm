<?php

namespace App\EventListener;

use Symfony\Component\Asset\Packages;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener]
readonly class ShowReactJsonView
{
    public function __construct(private Packages $assets) {}

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        if (!$response instanceof JsonResponse
            || !in_array('text/html', $request->getAcceptableContentTypes(), true)) {
            return;
        }
        $json = $response->getContent();
        $jsonLength = mb_strlen($json);
        $assetsUrl = collect(['react-json-view', 'react', 'react-dom'])
            ->mapWithKeys(fn($asset) => [$asset => $this->assets->getUrl("/assets/$asset.js")]);
        $event->setResponse(new Response(<<<HTML
        <html>
            <head>
                <title>$jsonLength bytes of json response for route {$request->getPathInfo()}</title>
            </head>
            <body>
                <h4>$jsonLength bytes</h4>
                <div id="root"></div>
                <script type="module">
                    import ReactJsonView from '{$assetsUrl['react-json-view']}';
                    import { createElement } from '{$assetsUrl['react']}';
                    import { createRoot } from '{$assetsUrl['react-dom']}';

                    const root = createRoot(document.getElementById('root'));
                    root.render(createElement(ReactJsonView.default, { src: $json, quotesOnKeys: false }));
                </script>
                <style>
                    .object-content {
                        content-visibility: auto;
                    }
                </style>
            </body>
        </html>
        HTML));
    }
}
