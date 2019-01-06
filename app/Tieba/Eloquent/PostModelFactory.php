<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\IndexModel;

class PostModelFactory
{
    public static function newThread(int $fid): ThreadModel
    {
        return (new ThreadModel())->setForumID($fid);
    }

    public static function newReply(int $fid): ReplyModel
    {
        return (new ReplyModel())->setForumID($fid);
    }

    public static function newSubReply(int $fid): SubReplyModel
    {
        return (new SubReplyModel())->setForumID($fid);
    }

    public static function getPostsModelByForumID($fid): array
    {
        return [
            'thread' => self::newThread($fid),
            'reply' => self::newReply($fid),
            'subReply' => self::newSubReply($fid)
        ];
    }

    public static function getThreadByID(int $tid): ThreadModel
    {
        $fid = (new IndexModel())->where('tid', $tid)->value('fid');
        return self::newThread($fid);
    }

    public static function getReplyByID(int $pid): ReplyModel
    {
        $fid = (new IndexModel())->where('pid', $pid)->value('fid');
        return self::newReply($fid);
    }

    public static function getSubReplyByID(int $spid): SubReplyModel
    {
        $fid = (new IndexModel())->where('spid', $spid)->value('fid');
        return self::newSubReply($fid);
    }
}