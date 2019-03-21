<?php

namespace App\Tieba\Eloquent;

use Illuminate\Database\Eloquent\Model;

class IndexModel extends Model
{
    use InsertOnDuplicateKey;

    protected $table = 'tbm_postsIndex';

    protected $guarded = [];

    public $updateExpectFields = [
        'created_at'
    ];
}
