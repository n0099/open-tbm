<?php

namespace App\Eloquent;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class NullableBooleanAttributeCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): bool
    {
        return $value === 1;
    }

    /** @psalm-return 0|1 */
    public function set($model, string $key, $value, array $attributes): int
    {
        \is_bool($value) || $value = (bool) $value;
        return $value === true ? 1 : 0;
    }
}
