<?php

namespace App\Tieba\Eloquent;

class SubReplyModel extends PostModel
{
    public function post()
    {
        return $this->belongsTo(ThreadModel::class, 'tid', 'tid');
    }

    public function reply()
    {
        return $this->belongsTo(ReplyModel::class, 'pid', 'pid');
    }

    public function scopeTid($query, $tid): \Illuminate\Database\Eloquent\Builder
    {
        return $this->scopeIDType($query, 'tid', $tid);
    }

    public function scopePid($query, $pid): \Illuminate\Database\Eloquent\Builder
    {
        return $this->scopeIDType($query, 'pid', $pid);
    }

    public function scopeSpid($query, $spid): \Illuminate\Database\Eloquent\Builder
    {
        return $this->scopeIDType($query, 'spid', $spid);
    }

    public function toPost(): \App\Tieba\Post
    {
        return new \App\Tieba\SubReply($this);
    }
}
