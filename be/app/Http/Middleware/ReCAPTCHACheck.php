<?php

namespace App\Http\Middleware;

use App\Helper;
use ReCaptcha\ReCaptcha;
use ReCaptcha\RequestMethod\CurlPost;

class ReCAPTCHACheck
{
    public function handle(\Illuminate\Http\Request $request, \Closure $next): mixed
    {
        if (\App::environment('production')) {
            $reCAPTCHA = new ReCaptcha(config('services.recaptcha.secret'), new CurlPost());
            $requestReCAPTCHA = $request->input('reCAPTCHA');
            $isReCAPTCHAValid = \is_string($requestReCAPTCHA)
                && $reCAPTCHA->verify($requestReCAPTCHA, $request->ip())->isSuccess();
            Helper::abortAPIIfNot(40101, $isReCAPTCHAValid);
        }

        return $next($request);
    }
}
