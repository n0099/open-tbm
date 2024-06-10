<?php

namespace App\Http\Middleware;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DumpJsonResponse
{
    /**
     * @param \Closure(Request): (\Symfony\Component\HttpFoundation\Response) $next
     * @source https://github.com/laravel/framework/issues/3929#issuecomment-935123918
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        $response = $next($request);
        if ($response instanceof JsonResponse) {
            if ($request->accepts('text/html')) {
                return response(<<<HTML
                    <div id="root"></div>
                    <script type="module">
                        import ReactJsonView from 'https://cdn.jsdelivr.net/npm/@microlink/react-json-view@1.23.0/+esm';
                        import { createElement } from 'https://cdn.jsdelivr.net/npm/react@18.3.1/+esm';
                        import { createRoot } from 'https://cdn.jsdelivr.net/npm/react-dom@18.3.1/+esm';

                        const root = createRoot(document.getElementById('root'));
                        root.render(createElement(ReactJsonView.default, { src: $response->content(), quotesOnKeys: false }));
                    </script>
                    HTML);
            }
            $response->setEncodingOptions(JSON_PRETTY_PRINT);
        }

        return $response;
    }
}
