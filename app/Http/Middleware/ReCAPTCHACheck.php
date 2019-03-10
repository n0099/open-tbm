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
        $requestReCAPTCHA = $request->input('reCAPTCHA');
        $isReCAPTCHAVaild = $requestReCAPTCHA == null ? false : $reCAPTCHA->verify($requestReCAPTCHA, $request->ip())->isSuccess();
        if ($isReCAPTCHAVaild) {
            return $next($request);
        } else {
            abort(400);
        }
    }
}
