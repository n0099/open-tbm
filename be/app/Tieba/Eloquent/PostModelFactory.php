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

    public static function newReplyContent(int $fid): ReplyContentModel
    {
        return (new ReplyContentModel())->setFid($fid);
    }

    public static function newSubReply(int $fid): SubReplyModel
    {
        return (new SubReplyModel())->setFid($fid);
    }

    public static function newSubReplyContent(int $fid): SubReplyContentModel
    {
        return (new SubReplyContentModel())->setFid($fid);
    }

    /**
     * @return (ReplyModel|SubReplyModel|ThreadModel)[]
     * @plasm-return array{thread: ThreadModel, reply: ReplyModel, subReply: SubReplyModel}
     */
    #[ArrayShape([
        'thread' => ThreadModel::class,
        'reply' => ReplyModel::class,
        'subReply' => SubReplyModel::class
    ])] public static function getPostModelsByFid(int $fid): array
    {
        return array_combine(
            Helper::POST_TYPES,
            [self::newThread($fid), self::newReply($fid), self::newSubReply($fid)]
        );
    }
}
