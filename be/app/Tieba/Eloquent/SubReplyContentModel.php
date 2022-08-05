<?php

namespace App\Tieba\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SubReplyContentModel extends PostModel
{
    protected $primaryKey = 'spid';

    protected static array $fields = ['spid', 'content'];

    public function scopeSpid(Builder $query, Collection|array|int $spid): Builder
    {
        return $this->scopeIDType($query, 'spid', $spid);
    }
}
