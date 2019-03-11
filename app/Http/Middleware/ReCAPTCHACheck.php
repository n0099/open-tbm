<?php

namespace App\Http\Middleware;

class ReCAPTCHACheck
{
    public function handle(\Illuminate\Http\Request $request, \Closure $next)
    {
        $reCAPTCHA = new \ReCaptcha\ReCaptcha(env('reCAPTCHA_SECRET_KEY'));
        $requestReCAPTCHA = $request->input('reCAPTCHA');
        $isReCAPTCHAValid = $requestReCAPTCHA == null ? false : $reCAPTCHA->verify($requestReCAPTCHA, $request->ip())->isSuccess();
        if ($isReCAPTCHAValid) {
            return $next($request);
        } else {
            abort(400);
        }
    }
}
