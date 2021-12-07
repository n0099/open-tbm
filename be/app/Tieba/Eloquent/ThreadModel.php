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
    protected $primaryKey = 'tid';

    protected $casts = [
        'agreeInfo' => 'array',
        'zanInfo' => 'array',
        'location' => 'array'
    ];

    protected array $fields = [
        'id',
        'tid',
        'firstPid',
        'threadType',
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
        'location',
        'agreeInfo',
        'zanInfo',
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
        'title',
        'postTime',
        'authorUid',
        'created_at'
    ];

    public function replies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReplyModel::class, 'tid', 'tid');
    }

    public function toPost(): Post
    {
        return new Thread($this);
    }
}
