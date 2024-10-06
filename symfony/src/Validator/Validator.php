<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class Validator
{
    public function __construct(private ValidatorInterface $validator) {}

    public function validate($value, Constraint|array|null $constraints): void
    {
        $errors = $this->validator->validate($value, $constraints);
        if ($errors->count() !== 0) {
            throw new ValidationFailedException($value, $errors);
        }
    }
}
