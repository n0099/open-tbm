<?php

namespace App\Tests\Validator;

use App\Validator\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[CoversClass(Validator::class)]
class ValidatorTest extends KernelTestCase
{
    private Validator $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = static::getContainer()->get(Validator::class);
    }

    public function test(): void
    {
        $this->expectNotToPerformAssertions();
        $this->sut->validate('12345', new Assert\Type('digit'));
    }

    public function testInvalid(): void
    {
        $this->expectException(ValidationFailedException::class);
        $this->expectExceptionMessage('This value should be of type digit.');
        $this->sut->validate('abcde', new Assert\Type('digit'));
    }
}
