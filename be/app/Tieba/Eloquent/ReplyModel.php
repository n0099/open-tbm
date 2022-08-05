<?php

namespace App\Tieba\Eloquent;

use App\Tieba\Post\Post;
use App\Tieba\Post\Reply;
use Illuminate\Database\Eloquent\Builder;

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

    public function scopePid(Builder $query, $pid): Builder
    {
        return $this->scopeIDType($query, 'pid', $pid);
    }

    public function toPost(): Post
    {
        return new Reply($this);
    }
}
