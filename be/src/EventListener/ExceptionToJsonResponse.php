<?php

namespace App\EventListener;

use App\Helper;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener]
readonly class ExceptionToJsonResponse
{
    public function __construct(private SerializerInterface $serializer) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($exception instanceof HttpException
            && collect(Helper::ERROR_STATUS_CODE_INFO)
                ->flatMap(static fn(array $codes) => array_keys($codes))
                ->contains($exception->getCode())) {
            $event->setResponse(JsonResponse::fromJsonString($exception->getMessage()));
        } elseif ($exception instanceof ValidationFailedException) {
            $event->setResponse(JsonResponse::fromJsonString(
                // https://github.com/symfony/serializer/blob/7.1/Normalizer/ConstraintViolationListNormalizer.php
                $this->serializer->serialize([
                    'errorCode' => 40000,
                    'errorInfo' => $exception->getViolations(),
                ], 'json'),
            ));
        }
    }
}
