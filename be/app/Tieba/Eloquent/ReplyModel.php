<?php

namespace App\Tieba\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReplyModel extends PostModel
{
    protected $primaryKey = 'pid';

    protected static array $fields = [
        'tid',
        'pid',
        'floor',
        'authorUid',
        'authorManagerType',
        'authorExpGrade',
        'subReplyNum',
        'postTime',
        'isFold',
        'agreeNum',
        'disagreeNum',
        'geolocation',
        'signatureId',
        ...parent::TIMESTAMP_FIELD_NAMES
    ];

    public function thread(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ThreadModel::class, 'tid', 'tid');
    }

    public function subReplies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubReplyModel::class, 'pid', 'pid');
    }

    public function scopePid(Builder $query, Collection|array|int $pid): Builder
    {
        return $this->scopeIDType($query, 'pid', $pid);
    }
}
