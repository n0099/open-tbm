<?php

namespace App\Tieba\Eloquent;

use App\Tieba\Post\Post;
use App\Tieba\Post\Thread;

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

    protected static array $fields = [
        'tid',
        'firstPid',
        'threadType',
        'stickyType',
        'topicType',
        'isGood',
        'title',
        'authorUid',
        'authorManagerType',
        'postTime',
        'latestReplyTime',
        'latestReplierUid',
        'replyNum',
        'viewNum',
        'shareNum',
        'agreeNum',
        'disagreeNum',
        'zanInfo',
        'location',
        'authorPhoneType',
        'createdAt',
        'updatedAt'
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
