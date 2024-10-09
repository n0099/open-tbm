<?php

namespace App\Tests\EventListener;

use App\EventListener\ExceptionToJsonResponse;
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
        $event = new ExceptionEvent(
            self::$kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new HttpException(400, message: 'test', code: 40001),
        );
        ($this->sut)($event);
        self::assertEquals('test', $event->getResponse()->getContent());
    }

    public function testValidationFailedException(): void
    {
        try {
            $this->validator->validate('1', new Assert\Type('int'));
        } catch (ValidationFailedException $e) {
            $event = new ExceptionEvent(self::$kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, $e);
            ($this->sut)($event);
            $responseJSON = \Safe\json_decode($event->getResponse()->getContent());
            self::assertEquals(40000, $responseJSON->errorCode);
            self::assertEquals('This value should be of type int.', $responseJSON->errorInfo->detail);
        }
    }
}
