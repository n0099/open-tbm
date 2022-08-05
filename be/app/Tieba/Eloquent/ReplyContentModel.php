<?php

namespace App\Tieba\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReplyContentModel extends PostModel
{
    protected $primaryKey = 'pid';

    protected static array $fields = ['pid', 'content'];

    public function scopePid(Builder $query, Collection|array|int $pid): Builder
    {
        return $this->scopeIDType($query, 'pid', $pid);
    }
}
