<?php

namespace App\Eloquent;

use Illuminate\Database\Eloquent\Model;

class BilibiliVoteModel extends Model
{
    use InsertOnDuplicateKey;

    protected $table = 'tbm_bilibiliVote';

    protected $guarded = [];
}
