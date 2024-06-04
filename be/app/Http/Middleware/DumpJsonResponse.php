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
        if ($response instanceof JsonResponse && $request->accepts('text/html')) {
            $response->setEncodingOptions(JSON_PRETTY_PRINT);
            dump($response->content());
            return response('');
        }

        return $response;
    }
}
