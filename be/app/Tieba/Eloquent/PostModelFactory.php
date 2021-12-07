<?php

namespace App\Tieba\Eloquent;

use JetBrains\PhpStorm\ArrayShape;

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

    /**
     * @param int $fid
     * @return array{thread: ThreadModel, reply: ReplyModel, subReply: SubReplyModel}
     */
    #[ArrayShape([
        'thread' => "\App\Tieba\Eloquent\ThreadModel",
        'reply' => "\App\Tieba\Eloquent\ReplyModel",
        'subReply' => "\App\Tieba\Eloquent\SubReplyModel"
    ])] public static function getPostModelsByFid(int $fid): array
    {
        return [
            'thread' => self::newThread($fid),
            'reply' => self::newReply($fid),
            'subReply' => self::newSubReply($fid)
        ];
    }

    public static function getThreadByID(int $tid): ThreadModel
    {
        $fid = (new IndexModel())::where('tid', $tid)->value('fid');
        return self::newThread($fid);
    }

    public static function getReplyByID(int $pid): ReplyModel
    {
        $fid = (new IndexModel())::where('pid', $pid)->value('fid');
        return self::newReply($fid);
    }

    public static function getSubReplyByID(int $spid): SubReplyModel
    {
        $fid = (new IndexModel())::where('spid', $spid)->value('fid');
        return self::newSubReply($fid);
    }
}
