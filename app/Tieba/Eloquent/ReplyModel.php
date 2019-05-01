<?php

namespace App\Tieba\Eloquent;

use App\Tieba\Post\Post;
use App\Tieba\Post\Reply;
use Illuminate\Database\Eloquent\Builder;

class ReplyModel extends PostModel
{
    protected $casts = [
        'content' => 'array',
        'agreeInfo' => 'array',
        'signInfo' => 'array',
        'tailInfo' => 'array'
    ];

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
        'location',
        'agreeInfo',
        'signInfo',
        'tailInfo',
        'clientVersion',
        'created_at',
        'updated_at'
    ];

    protected $hidedFields = [
        'id',
        'clientVersion'
    ];

    public $updateExpectFields = [
        'tid',
        'pid',
        'floor',
        'postTime',
        'authorUid',
        'created_at'
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

    public function toPost(): Post
    {
        return new Reply($this);
    }
}
