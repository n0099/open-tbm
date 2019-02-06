<?php

namespace App\Tieba\Eloquent;

use Illuminate\Database\Eloquent\Builder;

/**
 * Class Post
 * Model for every Tieba thread post
 *
 * @package App\Tieba\Eloquent
 */
class ThreadModel extends PostModel
{
    protected $fields = [
        'id',
        'tid',
        'firstPid',
        'isSticky',
        'isGood',
        'title',
        'authorUid',
        'authorManagerType',
        'postTime',
        'latestReplierUid',
        'latestReplyTime',
        'replyNum',
        'viewNum',
        'shareNum',
        'agreeInfo',
        'zanInfo',
        'locationInfo',
        'clientVersion',
        'created_at',
        'updated_at',
    ];

    protected $hidedFields = [
        'id',
        'clientVersion',
    ];

    public function replies()
    {
        return $this->hasMany(ReplyModel::class, 'tid', 'tid');
    }

    public function scopeTid(Builder $query, $tid): Builder
    {
        return $this->scopeIDType($query, 'tid', $tid);
    }

    public function toPost(): \App\Tieba\Post
    {
        return new \App\Tieba\Thread($this);
    }
}
