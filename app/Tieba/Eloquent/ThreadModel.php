<?php

namespace App\Tieba\Eloquent;

use App\Tieba\Post\Post;
use App\Tieba\Post\Thread;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class Post
 * Model for every Tieba thread post
 *
 * @package App\Tieba\Eloquent
 */
class ThreadModel extends PostModel
{
    protected $casts = [
        'agreeInfo' => 'array',
        'zanInfo' => 'array',
        'locationInfo' => 'array'
    ];

    protected $fields = [
        'id',
        'tid',
        'firstPid',
        'stickyType',
        'isGood',
        'topicType',
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
        'updated_at'
    ];

    protected $hidedFields = [
        'id',
        'clientVersion'
    ];

    public $updateExpectFields = [
        'tid',
        'title',
        'postTime',
        'authorUid',
        'created_at'
    ];

    public function replies()
    {
        return $this->hasMany(ReplyModel::class, 'tid', 'tid');
    }

    public function scopeTid(Builder $query, $tid): Builder
    {
        return $this->scopeIDType($query, 'tid', $tid);
    }

    public function toPost(): Post
    {
        return new Thread($this);
    }
}
