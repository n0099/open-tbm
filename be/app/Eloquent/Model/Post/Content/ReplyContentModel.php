<?php

namespace App\Eloquent\Model\Post\Content;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReplyContentModel extends PostContentModel
{
    protected $primaryKey = 'pid';

    protected static array $publicFields = ['pid', 'protoBufBytes'];

    public function scopePid(Builder $query, Collection|array|int $pid): Builder
    {
        return $this->scopeIDType($query, 'pid', $pid);
    }
}
