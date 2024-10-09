<?php

namespace App\Tests\Exceptions;

use App\Exceptions\Handler;
use Illuminate\Container\Container;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

#[CoversClass(Handler::class)]
class HandlerTest extends TestCase
{
    private Handler $sut;
    private Factory $validatorFactory;
    private ReflectionMethod $convertValidationExceptionToResponse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new Handler(Container::getInstance());
        $this->validatorFactory = app(Factory::class);
        $this->convertValidationExceptionToResponse = new ReflectionMethod(
            Handler::class,
            'convertValidationExceptionToResponse',
        );
    }

    private function invokeConvertValidationExceptionToResponse(ValidationException $exception): Response
    {
        return $this->convertValidationExceptionToResponse->invoke($this->sut, $exception, null);
    }

    public function testNotConvertValidationExceptionToResponse(): void
    {
        $exception = new ValidationException($this->validatorFactory->make([], []), new Response('test'));
        $response = $this->invokeConvertValidationExceptionToResponse($exception);
        self::assertEquals('test', $response->getContent());
    }

    public function testConvertValidationExceptionToResponse(): void
    {
        $exception = new ValidationException($this->validatorFactory->make(['test' => 'int'], ['test' => 'int']));
        $response = $this->invokeConvertValidationExceptionToResponse($exception);
        $responseJSON = \Safe\json_decode($response->getContent());
        self::assertEquals(40000, $responseJSON->errorCode);
        self::assertEquals('The test field must be an integer.', $responseJSON->errorInfo->test[0]);
    }
}
