<?php

namespace App\Tieba\Eloquent;

use App\Helper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SubReplyModel extends PostModel
{
    protected $primaryKey = 'spid';

    protected $casts = [
        'content' => 'array'
    ];

    protected static array $fields = [
        ...Helper::POST_ID,
        'authorUid',
        'authorManagerType',
        'authorExpGrade',
        'postTime',
        'agreeCount',
        'disagreeCount',
        ...parent::TIMESTAMP_FIELD_NAMES
    ];

    public function thread(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ThreadModel::class, 'tid', 'tid');
    }

    public function reply(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ReplyModel::class, 'pid', 'pid');
    }

    public function scopePid(Builder $query, Collection|array|int $pid): Builder
    {
        return $this->scopeIDType($query, 'pid', $pid);
    }

    public function scopeSpid(Builder $query, Collection|array|int $spid): Builder
    {
        return $this->scopeIDType($query, 'spid', $spid);
    }
}
