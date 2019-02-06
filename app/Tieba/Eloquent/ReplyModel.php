<?php

namespace App\Tieba\Eloquent;

use Illuminate\Database\Eloquent\Builder;

class ReplyModel extends PostModel
{
    protected $fields = [
        'id',
        'tid',
        'pid',
        'floor',
        'content',
        'authorUid',
        'authorManagerType',
        'authorExpGrade',
        'subReplyNum',
        'postTime',
        'isFold',
        'agreeInfo',
        'signInfo',
        'tailInfo',
        'clientVersion',
        'created_at',
        'updated_at',
    ];

    protected $hidedFields = [
        'id',
        'clientVersion',
    ];

    public function post()
    {
        return $this->belongsTo(ThreadModel::class, 'tid', 'tid');
    }

    public function subReplies()
    {
        return $this->hasMany(SubReplyModel::class, 'pid', 'pid');
    }

    public function scopeTid(Builder $query, $tid): Builder
    {
        return $this->scopeIDType($query, 'tid', $tid);
    }

    public function scopePid(Builder $query, $pid): Builder
    {
        return $this->scopeIDType($query, 'pid', $pid);
    }

    public function toPost(): \App\Tieba\Post
    {
        return new \App\Tieba\Reply($this);
    }
}
