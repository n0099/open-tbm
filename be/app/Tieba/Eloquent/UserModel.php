<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\InsertOnDuplicateKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class UserModel extends Model
{
    use InsertOnDuplicateKey;

    protected $table = 'tbm_tiebaUsers';

    protected $guarded = [];

    protected $casts = [
        'iconInfo' => 'array',
        'alaInfo' => 'array'
    ];

    protected array $fields = [
        'id',
        'uid',
        'name',
        'displayName',
        'avatarUrl',
        'gender',
        'fansNickname',
        'iconInfo',
        'alaInfo'
    ];

    protected array $hidedFields = [
        'id',
        'alaInfo',
        'created_at',
        'updated_at'
    ];

    public array $updateExpectFields = [
        'created_at'
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

    public function scopeHidePrivateFields(Builder $query): Builder
    {
        return $query->select(array_diff($this->fields, $this->hidedFields));
    }
}
