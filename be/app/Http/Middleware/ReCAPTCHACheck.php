<?php

namespace App\Http\Middleware;

use App\Helper;
use Illuminate\Http\Request;
use ReCaptcha\ReCaptcha;
use ReCaptcha\RequestMethod\CurlPost;

class ReCAPTCHACheck
{
    /** @param \Closure(Request): (\Symfony\Component\HttpFoundation\Response) $next */
    public function handle(Request $request, \Closure $next): mixed
    {
        /** @var string $secret */
        $secret = config('services.recaptcha.secret');
        if ($secret !== '') {
            $reCAPTCHA = new ReCaptcha($secret, new CurlPost());
            $requestReCAPTCHA = $request->input('reCAPTCHA');
            $isReCAPTCHAValid = \is_string($requestReCAPTCHA)
                && $reCAPTCHA->verify($requestReCAPTCHA, $request->ip())->isSuccess();
            Helper::abortAPIIfNot(40101, $isReCAPTCHAValid);
        }

        return $next($request);
    }
}
