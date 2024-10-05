<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Serializer\SerializerInterface;

#[AsEventListener]
readonly class SerializeToJson
{
    public function __construct(private SerializerInterface $serializer) {}

    public function __invoke(ViewEvent $event): void
    {
        $event->setResponse(JsonResponse::fromJsonString(
            $this->serializer->serialize($event->getControllerResult(), 'json')
        ));
    }
}
