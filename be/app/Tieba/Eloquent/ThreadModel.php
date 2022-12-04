<?php

namespace App\Tieba\Eloquent;

/**
 * Class Post
 * Model for every Tieba thread post
 *
 * @package App\Tieba\Eloquent
 */
class ThreadModel extends PostModel
{
    protected $primaryKey = 'tid';

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
        'replyCount',
        'viewCount',
        'shareCount',
        'agreeCount',
        'disagreeCount',
        'zan',
        'geolocation',
        'authorPhoneType',
        ...parent::TIMESTAMP_FIELD_NAMES
    ];

    public function replies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReplyModel::class, 'tid', 'tid');
    }
}
