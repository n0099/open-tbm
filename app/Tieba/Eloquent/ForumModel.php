<?php

namespace App\Tieba\Eloquent;

use Illuminate\Database\Eloquent\Model;

class ForumModel extends Model
{
    protected $table = 'tbm_forumsInfo';

    protected $guarded = [];

    public static function getName($fid)
    {
        return self::where('fid', $fid)->value('name');
    }

    public static function getFid($fourmName)
    {
        return self::where('name', $fourmName)->value('fid');
    }
}