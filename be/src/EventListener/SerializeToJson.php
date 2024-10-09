<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Serializer\SerializerInterface;

// phpcs:disable Generic.Files.LineLength
// https://github.com/symfony/twig-bridge/blob/d63fde6a6142ffbab5fe6ee252b668b9485bfc0d/EventListener/TemplateAttributeListener.php#L66
#[AsEventListener(priority: -129)]
readonly class SerializeToJson
{
    public function __construct(private SerializerInterface $serializer) {}

    public function __invoke(ViewEvent $event): void
    {
        $event->setResponse(JsonResponse::fromJsonString(
            $this->serializer->serialize($event->getControllerResult(), 'json'),
        ));
    }
}
