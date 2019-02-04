<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\InsertOnDuplicateKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserModel extends Model
{
    use InsertOnDuplicateKey;

    protected $table = 'tbm_tiebaUsers';

    protected $guarded = [];

    protected $fields = [
        'id',
        'uid',
        'name',
        'displayName',
        'avatarUrl',
        'gender',
        'fansNickname',
        'iconInfo',
        'alaInfo',
    ];

    protected $hidedFields = [
        'id',
        'alaInfo',
        'created_at',
        'updated_at',
    ];

    public function scopeHidePrivateFields(Builder $query): Builder
    {
        return $query->select(array_diff($this->fields, $this->hidedFields));
    }
}
