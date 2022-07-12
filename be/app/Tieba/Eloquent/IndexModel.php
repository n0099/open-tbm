<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\ModelHelper;
use Illuminate\Database\Eloquent\Model;

class IndexModel extends Model
{
    use ModelHelper;

    protected $table = 'tbm_postsIndex';

    protected $guarded = [];
}
