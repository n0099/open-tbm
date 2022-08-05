<?php

namespace App\Tieba\Eloquent;

use App\Helper;
use App\Tieba\Post\Post;
use App\Tieba\Post\SubReply;
use Illuminate\Database\Eloquent\Builder;

class SubReplyModel extends PostModel
{
    protected $primaryKey = 'spid';

    protected $casts = [
        'content' => 'array'
    ];

    protected static array $fields = [
        ...Helper::POSTS_ID,
        'authorUid',
        'authorManagerType',
        'authorExpGrade',
        'postTime',
        'agreeNum',
        'disagreeNum',
        ...parent::TIMESTAMP_FIELD_NAMES
    ];

    public function thread(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ThreadModel::class, 'tid', 'tid');
    }

    public function reply(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ReplyModel::class, 'pid', 'pid');
    }

    public function scopePid(Builder $query, $pid): Builder
    {
        return $this->scopeIDType($query, 'pid', $pid);
    }

    public function scopeSpid(Builder $query, $spid): Builder
    {
        return $this->scopeIDType($query, 'spid', $spid);
    }

    public function toPost(): Post
    {
        return new SubReply($this);
    }
}
