<?php

namespace App\Eloquent;

use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModel
 */
trait ModelHasPublicField
{
    /**
     * @var list<string>
     */
    protected static array $publicFields;

    /**
     * @var list<string>
     */
    protected static array $hiddenFields = [];

    /**
     * @param Builder<TModel> $query
     * @return Builder<TModel>
     */
    public function scopeSelectPublicFields(Builder $query): Builder
    {
        return $query->select(array_diff(static::$publicFields, static::$hiddenFields));
    }
}
