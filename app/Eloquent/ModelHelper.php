<?php

namespace App\Eloquent;

use Illuminate\Database\Eloquent\Builder;

trait ModelHelper
{
    public function scopeOrderByMulti(Builder $query, array $orders): Builder
    {
        foreach ($orders as $orderBy => $orderDirection) {
            $query = $query->orderBy($orderBy, $orderDirection);
        }
        return $query;
    }
}
