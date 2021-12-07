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

    protected array $fields = [
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

    protected array $hidedFields = [
        'id',
        'clientVersion'
    ];

    public array $updateExpectFields = [
        'tid',
        'pid',
        'floor',
        'postTime',
        'authorUid',
        'created_at'
    ];

    public function post(): \Illuminate\Database\Eloquent\Relations\BelongsTo
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
