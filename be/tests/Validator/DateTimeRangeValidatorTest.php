<?php

namespace App\Tests\Validator;

use App\Validator\DateTimeRange;
use App\Validator\DateTimeRangeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

#[CoversClass(DateTimeRangeValidator::class)]
class DateTimeRangeValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new DateTimeRangeValidator();
    }

    public function testValid(): void
    {
        $this->validator->validate(null, new DateTimeRange());
        $this->validator->validate('', new DateTimeRange());
        $this->validator->validate(',', new DateTimeRange());
        $this->validator->validate('2020-01-01,', new DateTimeRange());
        $this->validator->validate('2020-01-01,2025-01-01', new DateTimeRange());
        $this->assertNoViolation();
    }

    #[DataProvider('provideInvalid')]
    public function testInvalid($value, string $formattedValue): void
    {
        $this->validator->validate($value, new DateTimeRange());
        $this->buildViolation((new DateTimeRange())->message)
            ->setParameter('{{ value }}', $formattedValue)
            ->assertRaised();
    }

    public static function provideInvalid(): array
    {
        return [
            [20200101, '20200101'],
            ['2025-01-01,2020-01-01', '"2025-01-01,2020-01-01"']
        ];
    }

    public function testWrongConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        (new DateTimeRangeValidator())->validate(null, new Assert\NotBlank());
    }
}
