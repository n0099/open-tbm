<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\ModelWithHiddenFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserModel extends ModelWithHiddenFields
{
    protected $table = 'tbmc_user';

    protected $guarded = [];

    protected $casts = [
        'icon' => 'array'
    ];

    protected static array $fields = [
        'uid',
        'name',
        'displayName',
        'portrait',
        'portraitUpdatedAt',
        'gender',
        'fansNickname',
        'icon',
        'ipGeolocation',
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
