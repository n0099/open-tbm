<?php

namespace App\Eloquent;

/**
 * Class Post
 * Model for every Tieba thread post
 *
 * @package App\Eloquent
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
}
