<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\ModelHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class UserModel extends Model
{
    use ModelHelper;

    protected $table = 'tbm_tiebaUsers';

    protected $guarded = [];

    protected $casts = [
        'iconInfo' => 'array'
    ];

    protected array $fields = [
        'id',
        'uid',
        'name',
        'displayName',
        'avatarUrl',
        'gender',
        'fansNickname',
        'iconInfo'
    ];

    protected array $hidedFields = [
        'id',
        'createdAt',
        'updatedAt'
    ];

    public function scopeUid(Builder $query, $uid): Builder
    {
        if (\is_int($uid)) {
            return $query->where('uid', $uid);
        }
        if (\is_array($uid) || $uid instanceof Collection) {
            return $query->whereIn('uid', $uid);
        }
        throw new \InvalidArgumentException('Uid must be int or array');
    }
}
