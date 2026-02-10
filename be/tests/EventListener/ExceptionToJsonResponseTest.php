<?php

namespace App\Tests\EventListener;

use App\EventListener\ExceptionToJsonResponse;
use App\Utils;
use App\Validator\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[CoversClass(ExceptionToJsonResponse::class)]
class ExceptionToJsonResponseTest extends KernelTestCase
{
    private ExceptionToJsonResponse $sut;
    private Validator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $container = static::getContainer();
        $this->sut = $container->get(ExceptionToJsonResponse::class);
        $this->validator = $container->get(Validator::class);
    }

    public function testHttpException(): void
    {
        foreach (Utils::ERROR_STATUS_CODE_INFO as $statusCode => $errors) {
            foreach ($errors as $errorCode => $errorInfo) {
                $event = new ExceptionEvent(
                    self::$kernel,
                    new Request(),
                    HttpKernelInterface::MAIN_REQUEST,
                    new HttpException($statusCode, message: $errorInfo, code: $errorCode),
                );
                ($this->sut)($event);
                $response = $event->getResponse();
                self::assertEquals($errorInfo, $response->getContent());
                self::assertEquals($statusCode, $response->getStatusCode());
            }
        }
    }

    public function testValidationFailedException(): void
    {
        try {
            $this->validator->validate('1', new Assert\Type('int'));
        } catch (ValidationFailedException $e) {
            $event = new ExceptionEvent(self::$kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, $e);
            ($this->sut)($event);
            $response = $event->getResponse();
            self::assertEquals(400, $response->getStatusCode());
            $responseJSON = \Safe\json_decode($response->getContent());
            self::assertEquals(40000, $responseJSON->errorCode);
            self::assertEquals('This value should be of type int.', $responseJSON->errorInfo->detail);
        }
    }
}
