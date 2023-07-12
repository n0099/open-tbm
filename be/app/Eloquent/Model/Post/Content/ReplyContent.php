<?php

namespace App\Eloquent\Model\Post\Content;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReplyContent extends PostContent
{
    protected $primaryKey = 'pid';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->publicFields = ['pid', 'protoBufBytes'];
    }

    public function scopePid(Builder $query, Collection|array|int $pid): Builder
    {
        return $this->scopeIDType($query, 'pid', $pid);
    }
}
