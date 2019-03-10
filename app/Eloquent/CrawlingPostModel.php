<?php

namespace App\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CrawlingPostModel extends Model
{
    protected $table = 'tbm_crawlingPosts';

    protected $guarded = [];

    public $timestamps = false;
}