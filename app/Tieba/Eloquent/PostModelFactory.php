<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\IndexModel;

class PostModelFactory
{
    public static function newThread(int $fid): ThreadModel
    {
        return (new ThreadModel())->setForumId($fid);
    }

    public static function newReply(int $fid): ReplyModel
    {
        return (new ReplyModel())->setForumId($fid);
    }

    public static function newSubReply(int $fid): SubReplyModel
    {
        return (new SubReplyModel())->setForumId($fid);
    }

    public static function getThreadById(int $tid): ThreadModel
    {
        $fid = (new IndexModel())->where('tid', $tid)->value('fid');
        return self::newThread($fid);
    }

    public static function getReplyById(int $pid): ReplyModel
    {
        $fid = (new IndexModel())->where('pid', $pid)->value('fid');
        return self::newReply($fid);
    }

    public static function getSubReplyById(int $spid): SubReplyModel
    {
        $fid = (new IndexModel())->where('spid', $spid)->value('fid');
        return self::newSubReply($fid);
    }
}