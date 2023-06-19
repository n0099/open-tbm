<?php

namespace App\Eloquent\Model;

use App\Eloquent\ModelHasPublicField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ForumModel extends Model
{
    use ModelHasPublicField;

    protected $table = 'tbm_forum';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->publicFields = ['fid', 'name'];
    }

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
