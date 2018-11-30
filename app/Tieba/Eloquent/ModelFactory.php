<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\IndexModel;

class ModelFactory
{
    public static function newThread($fid): PostModel {
        return (new ThreadModel())->setForumId($fid);
    }

    public static function newReply($fid): PostModel {
        return (new ReplyModel())->setForumId($fid);
    }

    public static function newSubReply($fid): PostModel {
        return (new SubReplyModel())->setForumId($fid);
    }

    public static function getThreadById($tid): PostModel {
        $fid = (new IndexModel())->where('tid', $tid)->value('fid');
        return self::newThread($fid);
    }

    public static function getReplyById($pid): PostModel {
        $fid = (new IndexModel())->where('pid', $pid)->value('fid');
        return self::newReply($fid);
    }

    public static function getSubReplyById($spid): PostModel {
        $fid = (new IndexModel())->where('spid', $spid)->value('fid');
        return self::newSubReply($fid);
    }
}