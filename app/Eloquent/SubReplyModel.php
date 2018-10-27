<?php

namespace App\Eloquent;

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

    public function scopeTid($query, int $tid)
    {
        return $query->where('tid', $tid);
    }

    public function scopePid($query, int $pid)
    {
        return $query->where('pid', $pid);
    }

    public function scopeSpid($query, int $spid)
    {
        return $query->where('spid', $spid);
    }
}
