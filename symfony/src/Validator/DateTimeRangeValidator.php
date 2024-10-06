<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class DateTimeRangeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DateTimeRange) {
            throw new UnexpectedTypeException($constraint, DateTimeRange::class);
        }
        if (null === $value || '' === $value) {
            return;
        }

        $values = explode(',', $value);
        $errors = $this->context->getValidator()->validate($values, new Assert\Count(2));
        $errors->addAll($this->context->getValidator()->validate(
            $values[0],
            [new Assert\DateTime(), new Assert\LessThanOrEqual($values[1])],
        ));
        $errors->addAll($this->context->getValidator()->validate(
            $values[1],
            [new Assert\DateTime(), new Assert\GreaterThanOrEqual($values[0])],
        ));

        if ($errors->count() !== 0) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
