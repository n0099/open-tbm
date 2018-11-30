<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\InsertOnDuplicateKey;
use Illuminate\Database\Eloquent\Model;

class UserModel extends Model
{
    use InsertOnDuplicateKey;

    protected $table = 'tbm_tiebaUsers';

    protected $guarded = [];
}
