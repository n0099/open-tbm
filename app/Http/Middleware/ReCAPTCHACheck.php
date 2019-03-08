<?php

namespace App\Http\Middleware;

class ReCAPTCHACheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(\Illuminate\Http\Request $request, \Closure $next)
    {
        $reCAPTCHA = new \ReCaptcha\ReCaptcha(env('reCAPTCHA_SECRET_KEY'));
        if ($reCAPTCHA->verify($request->input('reCAPTCHA'), $request->ip())) {
            return $next($request);
        } else {
            abort(400);
        };
    }
}
