<?php

namespace App\Http\Middleware;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DumpJsonResponse
{
    /** @param \Closure(Request): (Response) $next */
    public function handle(Request $request, \Closure $next): Response
    {
        $response = $next($request);
        if ($response instanceof JsonResponse) {
            if ($request->accepts('text/html')) {
                $json = $response->content();
                $jsonLength = mb_strlen($json);
                $assetsUrl = collect(['react-json-view', 'react', 'react-dom'])
                    ->mapWithKeys(fn($asset) => [$asset => url("/assets/$asset.js")]);
                return response(<<<HTML
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
                    HTML);
            }
            // https://github.com/laravel/framework/issues/3929#issuecomment-935123918
            $response->setEncodingOptions(JSON_PRETTY_PRINT);
        }

        return $response;
    }
}
