<?php

namespace App\Eloquent;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class NullableNumericAttributeCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?int
    {
        return $value ?? 0;
    }

    public function set($model, string $key, $value, array $attributes): ?int
    {
        \is_int($value) || $value = (int) $value;
        return $value === 0 ? null : $value;
    }
}
