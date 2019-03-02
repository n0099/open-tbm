<?php
require 'core.php';
ini_set('memory_limit', '1024M');
$time = microtime(true);

$replies = $sql -> query("SELECT * FROM tbmonitor_reply_pic") -> fetch_all(MYSQLI_ASSOC);

foreach ($replies as $reply) {
    preg_match_all('/<img.*?src\="(.*?)"/', $reply['content'], $preg_matchs);
    foreach ($preg_matchs[1] as $match) {
        echo "{$match}\n";
        $sql -> query("INSERT INTO `tbmonitor_pics`(`tid`, `pid`, `author`, `content`, `floor`, `lzl_num`, `reply_time`) VALUES ({$reply['tid']},{$reply['pid']},\"{$reply['author']}\",\"{$match}\",{$reply['floor']},{$reply['lzl_num']},\"{$reply['reply_time']}\")");
    }
}

count($reply);

echo round(microtime(true) - $time, 4);