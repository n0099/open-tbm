<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener]
class PrettyJsonResponse
{
    public function __invoke(ResponseEvent $event): void
    {
        // https://github.com/laravel/framework/issues/3929#issuecomment-935123918
        $response = $event->getResponse();
        if ($response instanceof JsonResponse) {
            $response->setEncodingOptions(JSON_PRETTY_PRINT);
        }
    }
}
