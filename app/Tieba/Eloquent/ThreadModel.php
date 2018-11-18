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
    public function replies()
    {
        return $this->hasMany(ReplyModel::class, 'tid', 'tid');
    }

    public function scopeTid($query, int $tid)
    {
        return $query->where('tid', $tid);
    }

    public function toPost(): \App\Tieba\Post
    {
        return new \App\Tieba\Thread($this);
    }
}
