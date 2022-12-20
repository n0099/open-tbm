<?php

namespace App\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class ModelWithHiddenFields extends Model
{
    protected static array $fields;

    protected static array $hiddenFields = [];

    public function scopeHidePrivateFields(Builder $query): Builder
    {
        return $query->select(array_diff(static::$fields, static::$hiddenFields));
    }
}
