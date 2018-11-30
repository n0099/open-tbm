<?php

namespace App\Eloquent;

use Illuminate\Database\Eloquent\Model;

class IndexModel extends Model
{
    use InsertOnDuplicateKey;

    protected $table = 'tbm_postsIndex';

    protected $guarded = [];
}
