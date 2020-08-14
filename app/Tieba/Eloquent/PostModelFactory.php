<?php

namespace App\Tieba\Eloquent;

class PostModelFactory
{
    public static function newThread(int $fid): ThreadModel
    {
        return (new ThreadModel())->setFid($fid);
    }

    public static function newReply(int $fid): ReplyModel
    {
        return (new ReplyModel())->setFid($fid);
    }

    public static function newSubReply(int $fid): SubReplyModel
    {
        return (new SubReplyModel())->setFid($fid);
    }

    public static function getPostModelsByFid($fid): array
    {
        return [
            'thread' => static::newThread($fid),
            'reply' => static::newReply($fid),
            'subReply' => static::newSubReply($fid)
        ];
    }

    public static function getThreadByID(int $tid): ThreadModel
    {
        $fid = (new IndexModel())::where('tid', $tid)->value('fid');
        return static::newThread($fid);
    }

    public static function getReplyByID(int $pid): ReplyModel
    {
        $fid = (new IndexModel())::where('pid', $pid)->value('fid');
        return static::newReply($fid);
    }

    public static function getSubReplyByID(int $spid): SubReplyModel
    {
        $fid = (new IndexModel())::where('spid', $spid)->value('fid');
        return static::newSubReply($fid);
    }
}
