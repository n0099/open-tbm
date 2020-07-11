<?php

namespace App\Http\Middleware;

use App\Helper;

class ReCAPTCHACheck
{
    public function handle(\Illuminate\Http\Request $request, \Closure $next)
    {
        if (\App::environment('production')) {
            $reCAPTCHA = new \ReCaptcha\ReCaptcha(env('reCAPTCHA_SECRET_KEY'));
            $requestReCAPTCHA = $request->input('reCAPTCHA');
            $isReCAPTCHAValid = $requestReCAPTCHA == null ? false : $reCAPTCHA->verify($requestReCAPTCHA, $request->ip())->isSuccess();
            Helper::abortAPIIf(40101, ! $isReCAPTCHAValid);
        }

        return $next($request);
    }
}
