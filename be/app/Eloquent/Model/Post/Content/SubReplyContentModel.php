<?php

namespace App\Eloquent\Model\Post\Content;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SubReplyContentModel extends PostContentModel
{
    protected $primaryKey = 'spid';

    protected static array $publicFields = ['spid', 'protoBufBytes'];

    public function scopeSpid(Builder $query, Collection|array|int $spid): Builder
    {
        return $this->scopeIDType($query, 'spid', $spid);
    }
}
