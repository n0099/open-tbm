<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener]
readonly class ShowReactJsonView
{
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
        $reactJsonViewArtifact = $request->getBaseUrl() . '/react-json-view/dist/index.js';
        $event->setResponse(new Response(<<<HTML
        <html>
            <head>
                <title>$jsonLength bytes of json response for route {$request->getPathInfo()}</title>
            </head>
            <body>
                <h4>$jsonLength bytes</h4>
                <div id="root"></div>
                <script type="module">
                    import { createElement, createRoot, ReactJsonView } from '$reactJsonViewArtifact';

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
