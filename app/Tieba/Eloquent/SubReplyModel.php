<?php

namespace App\Tieba\Eloquent;

use Illuminate\Database\Eloquent\Builder;

class SubReplyModel extends PostModel
{
    protected $fields = [
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
        'updated_at',
    ];

    protected $hidedFields = [
        'id',
        'clientVersion',
    ];

    public $updateExpectFields = [
        'tid',
        'pid',
        'spid',
        'postTime',
        'authorUid',
        'created_at'
    ];

    public function post()
    {
        return $this->belongsTo(ThreadModel::class, 'tid', 'tid');
    }

    public function reply()
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

    public function toPost(): \App\Tieba\Post\Post
    {
        return new \App\Tieba\Post\SubReply($this);
    }
}
