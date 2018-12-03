<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\IndexModel;

class PostModelFactory
{
    public static function newThread(int $fid): PostModel
    {
        return (new ThreadModel())->setForumId($fid);
    }

    public static function newReply(int $fid): PostModel
    {
        return (new ReplyModel())->setForumId($fid);
    }

    public static function newSubReply(int $fid): PostModel
    {
        return (new SubReplyModel())->setForumId($fid);
    }

    public static function getThreadById(int $tid): PostModel
    {
        $fid = (new IndexModel())->where('tid', $tid)->value('fid');
        return self::newThread($fid);
    }

    public static function getReplyById(int $pid): PostModel
    {
        $fid = (new IndexModel())->where('pid', $pid)->value('fid');
        return self::newReply($fid);
    }

    public static function getSubReplyById(int $spid): PostModel
    {
        $fid = (new IndexModel())->where('spid', $spid)->value('fid');
        return self::newSubReply($fid);
    }
}