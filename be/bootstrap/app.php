<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withSingletons([
        Illuminate\Contracts\Debug\ExceptionHandler::class => App\Exceptions\Handler::class
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
