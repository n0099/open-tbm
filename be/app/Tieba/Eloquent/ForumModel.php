<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\ModelWithHiddenFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ForumModel extends ModelWithHiddenFields
{
    protected $table = 'tbm_forum';

    protected $guarded = [];

    protected static array $fields = ['fid', 'name'];

    public function scopeIsCrawling(Builder $query, bool $isCrawling): Builder
    {
        return $query->where('isCrawling', $isCrawling);
    }

    public function scopeFid(Builder $query, int $fid): Builder
    {
        return $query->where('fid', $fid);
    }

    public static function getName(int $fid): Collection
    {
        return self::where('fid', $fid)->value('name');
    }

    public static function getFid(string $forumName): Collection
    {
        return self::where('name', $forumName)->value('fid');
    }
}
