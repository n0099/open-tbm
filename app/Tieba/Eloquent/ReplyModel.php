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

    public function scopeTid($query, int $tid)
    {
        return $query->where('tid', $tid);
    }

    public function scopePid($query, int $pid)
    {
        return $query->where('pid', $pid);
    }

    public function toPost(): \App\Tieba\Post
    {
        return new \App\Tieba\Reply($this);
    }
}
