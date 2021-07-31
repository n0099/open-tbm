<?php

namespace App\Tieba\Eloquent;

use App\Tieba\Post\Post;
use App\Tieba\Post\SubReply;
use Illuminate\Database\Eloquent\Builder;

class SubReplyModel extends PostModel
{
    protected $primaryKey = 'spid';

    protected $casts = [
        'content' => 'array'
    ];

    protected array $fields = [
        'id',
        'tid',
        'pid',
        'spid',
        'content',
        'authorUid',
        'authorManagerType',
        'authorExpGrade',
        'postTime',
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
        'spid',
        'postTime',
        'authorUid',
        'created_at'
    ];

    public function post(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ThreadModel::class, 'tid', 'tid');
    }

    public function reply(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ReplyModel::class, 'pid', 'pid');
    }

    public function scopeTid(Builder $query, $tid): Builder
    {
        return $this->scopeIDType($query, 'tid', $tid);
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
