<?php

namespace App\Tieba\Eloquent;

class ReplyModel extends PostModel
{
    public function post()
    {
        return $this->belongsTo(ThreadModel::class, 'tid', 'tid');
    }

    public function subReplies()
    {
        return $this->hasMany(SubReplyModel::class, 'pid', 'pid');
    }

    public function scopeTid($query, $tid): \Illuminate\Database\Eloquent\Builder
    {
        return $this->scopeIDType($query, 'tid', $tid);
    }

    public function scopePid($query, $pid): \Illuminate\Database\Eloquent\Builder
    {
        return $this->scopeIDType($query, 'pid', $pid);
    }

    public function toPost(): \App\Tieba\Post
    {
        return new \App\Tieba\Reply($this);
    }
}
