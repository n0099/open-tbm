<?php

namespace App\Eloquent\Model;

use App\Eloquent\ModelHasPublicField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class UserModel extends Model
{
    use ModelHasPublicField;

    protected $table = 'tbmc_user';

    protected $guarded = [];

    protected $casts = [
        'icon' => 'array'
    ];

    protected static array $publicFields = [
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
