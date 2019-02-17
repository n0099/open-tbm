<?php

namespace App\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CrawlingPostModel extends Model
{
    protected $table = 'tbm_crawlingPosts';

    protected $guarded = [];

    public $timestamps = false;

    public function scopeType(Builder $query, $type): Builder
    {
        if (is_array($type)) {
            return $query->whereIn('type', $type);
        } else {
            return $query->where('type', $type);
        }
    }
}