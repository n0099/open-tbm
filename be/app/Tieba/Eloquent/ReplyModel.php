<?php

namespace App\Tieba\Eloquent;

use App\Tieba\Post\Post;
use App\Tieba\Post\Reply;
use Illuminate\Database\Eloquent\Builder;

class ReplyModel extends PostModel
{
    protected $primaryKey = 'pid';

    protected $casts = [
        'content' => 'array',
        'agreeInfo' => 'array',
        'signInfo' => 'array',
        'tailInfo' => 'array'
    ];

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
        'location',
        'signatureId',
        'createdAt',
        'updatedAt'
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
