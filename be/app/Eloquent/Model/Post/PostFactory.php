<?php

namespace App\Eloquent\Model\Post;

use App\Eloquent\Model\Post\Content\ReplyContent;
use App\Eloquent\Model\Post\Content\SubReplyContent;
use App\Helper;

class PostFactory
{
    public static function newThread(int $fid): Thread
    {
        return (new Thread())->setFid($fid);
    }

    public static function newReply(int $fid): Reply
    {
        return (new Reply())->setFid($fid);
    }

    public static function newReplyContent(int $fid): ReplyContent
    {
        return (new ReplyContent())->setFid($fid);
    }

    public static function newSubReply(int $fid): SubReply
    {
        return (new SubReply())->setFid($fid);
    }

    public static function newSubReplyContent(int $fid): SubReplyContent
    {
        return (new SubReplyContent())->setFid($fid);
    }

    /**
     * @return array{thread: Thread, reply: Reply, subReply: SubReply}
     */
    public static function getPostModelsByFid(int $fid): array
    {
        return array_combine(
            Helper::POST_TYPES,
            [self::newThread($fid), self::newReply($fid), self::newSubReply($fid)],
        );
    }
}
