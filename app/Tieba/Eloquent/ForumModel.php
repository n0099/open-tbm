<?php

namespace App\Tieba\Eloquent;

use Illuminate\Database\Eloquent\Model;

class ForumModel extends Model
{
    protected $table = 'tbm_forumsInfo';

    protected $guarded = [];

    public static function getName(int $fid): \Illuminate\Support\Collection
    {
        return self::where('fid', $fid)->value('name');
    }

    public static function getFid(string $fourmName): \Illuminate\Support\Collection
    {
        return self::where('name', $fourmName)->value('fid');
    }
}