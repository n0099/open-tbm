<?php

namespace App\Eloquent;

class ModelFactory
{
    public static function newThread($fid) {
        return (new ThreadModel())->setForumId($fid);
    }

    public static function newReply($fid) {
        return (new ReplyModel())->setForumId($fid);
    }

    public static function newSubReply($fid) {
        return (new SubReplyModel())->setForumId($fid);
    }

    public static function getThreadById($tid) {
        $fid = (new IndexModel())->where('tid', $tid)->value('fid');
        return self::newThread($fid);
    }

    public static function getReplyById($pid) {
        $fid = (new IndexModel())->where('pid', $pid)->value('fid');
        return self::newReply($fid);
    }

    public static function getSubReplyById($spid) {
        $fid = (new IndexModel())->where('spid', $spid)->value('fid');
        return self::newSubReply($fid);
    }
}