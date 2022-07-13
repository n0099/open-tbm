<?php

namespace App\Tieba\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IndexModel extends Model
{
    protected $table = 'tbm_postsIndex';

    protected $guarded = [];

    public function scopeOrderByMulti(Builder $query, array $orders): Builder
    {
        foreach ($orders as $orderBy => $orderDirection) {
            $query = $query->orderBy($orderBy, $orderDirection);
        }
        return $query;
    }
}
