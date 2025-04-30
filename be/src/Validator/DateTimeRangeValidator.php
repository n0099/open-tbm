<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class DateTimeRangeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DateTimeRange) {
            throw new UnexpectedTypeException($constraint, DateTimeRange::class);
        }
        if ($value === null || $value === '') {
            return;
        }
        $addViolation = fn() => $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $this->formatValue($value))
            ->addViolation();
        if (!is_string($value)) {
            $addViolation();
            return;
        }
        $values = array_map(static fn(string $value) => new \DateTimeImmutable($value), explode(',', $value));
        if (count($values) !== 2) {
            $addViolation();
            return;
        }
        if ($values[0] > $values[1]) {
            $addViolation();
        }
    }
}
