<?php

namespace App\Eloquent;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait ModelHasResourceAttribute
{
    protected function resourceAttribute(): Attribute
    {
        return Attribute::make(
        /**
         * @param resource|null $value
         * @return string
         */
            get: static fn ($value) => $value === null ? null : stream_get_contents($value)
        )->shouldCache();
    }
}
