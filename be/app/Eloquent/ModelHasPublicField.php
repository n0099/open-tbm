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
    protected array $publicFields = [];

    /**
     * @var list<string>
     */
    protected array $hiddenFields = [];

    /**
     * @param Builder<TModel> $query
     * @return Builder<TModel>
     */
    public function scopeSelectPublicFields(Builder $query): Builder
    {
        return $query->select(array_diff($this->publicFields, $this->hiddenFields));
    }
}
