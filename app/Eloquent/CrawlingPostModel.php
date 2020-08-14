<?php

namespace App\Eloquent;

use Illuminate\Database\Eloquent\Model;

class CrawlingPostModel extends Model
{
    protected $table = 'tbm_crawlingPosts';

    protected $guarded = [];

    public $timestamps = false;
}
