<?php

namespace App\Tieba\Eloquent;

use App\Helper;
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
     * @return PostModel[]
     * @plasm-return array{thread: ThreadModel, reply: ReplyModel, subReply: SubReplyModel}
     */
    #[ArrayShape([
        'thread' => ThreadModel::class,
        'reply' => ReplyModel::class,
        'subReply' => SubReplyModel::class
    ])] public static function getPostModelsByFid(int $fid): array
    {
        return array_combine(Helper::POST_TYPES, [self::newThread($fid), self::newReply($fid), self::newSubReply($fid)]);
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
