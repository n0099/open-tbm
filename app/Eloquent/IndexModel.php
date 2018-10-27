<?php

namespace App\Eloquent;

use Illuminate\Database\Eloquent\Model;

class IndexModel extends Model
{
    protected $table = 'tbm_index';

    protected $guarded = [];

    public static function getThreadModelByTid($tid) {
        $fid = (new IndexModel())->where('tid', $tid)->value('fid');
        return (new ThreadModel())->setForumId($fid);
    }

    public static function getReplyModelByPid($pid) {
        $fid = (new IndexModel())->where('pid', $pid)->value('fid');
        return (new ReplyModel())->setForumId($fid);
    }

    public static function getSubReplyModelBySpid($spid) {
        $fid = (new IndexModel())->where('spid', $spid)->value('fid');
        return (new SubReplyModel())->setForumId($fid);
    }
}