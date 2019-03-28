<?php

namespace App\Tieba\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ForumModel extends Model
{
    protected $table = 'tbm_forumsInfo';

    protected $guarded = [];

    protected $fields = [
        'id',
        'fid',
        'name'
    ];

    protected $hidedFields = [
        'id',
    ];

    public function scopeHidePrivateFields(Builder $query): Builder
    {
        return $query->select(array_diff($this->fields, $this->hidedFields));
    }

    public static function getName(int $fid): Collection
    {
        return static::where('fid', $fid)->value('name');
    }

    public static function getFid(string $fourmName): Collection
    {
        return static::where('name', $fourmName)->value('fid');
    }
}