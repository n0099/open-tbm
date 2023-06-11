<?php

namespace App\Eloquent\Model\Post;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SubReplyContentModel extends PostContentModel
{
    protected $primaryKey = 'spid';

    protected static array $fields = ['spid', 'content'];

    public function scopeSpid(Builder $query, Collection|array|int $spid): Builder
    {
        return $this->scopeIDType($query, 'spid', $spid);
    }
}
