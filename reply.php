<?php
require 'core.php';

// 百度账号BDUSS Cookie
$bduss = '';
$tieba = '';
$tieba_fid = get_tieba_fid($tieba);
$content_regex = $sql -> query('SELECT * FROM tbmonitor_reply_regex ORDER BY priority DESC') -> fetch_all(MYSQLI_ASSOC);
$sql_query_time = 'BETWEEN "' . date('Y-m-d', strtotime(date('Y-m-d') . '-1 day')) . '" AND "' . date('Y-m-d', strtotime(date('Y-m-d') . '+1 day')) . '"';
$posts = $sql -> query("SELECT * FROM tbmonitor_post WHERE forum=\"{$tieba}\" AND post_time {$sql_query_time} AND author != \"n0099\"") -> fetch_all(MYSQLI_ASSOC);
$replys = $sql -> query("SELECT * FROM tbmonitor_reply WHERE forum=\"{$tieba}\" AND content LIKE \"%n0099%\" AND reply_time {$sql_query_time} AND author != \"n0099\"") -> fetch_all(MYSQLI_ASSOC);
$lzls = $sql -> query("SELECT * FROM tbmonitor_lzl WHERE (forum=\"{$tieba}\" AND content LIKE \"%n0099%\" AND reply_time {$sql_query_time} AND author != \"n0099\") OR (pid IN (SELECT pid FROM tbmonitor_reply WHERE reply_time {$sql_query_time} AND author = \"n0099\") AND author != \"n0099\")") -> fetch_all(MYSQLI_ASSOC);

function post_reply($bduss, $tbs, $devices, $tieba, $fid, $tid, $pid = null, $content) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_COOKIE, "BDUSS={$bduss};");
    if (empty($pid)) {
        if ($devices == 0) {
            // WAP回贴
            $post_data = [
                'co' => $content,
                'src' => 1,
                'word' => $tieba,
                'tbs' => $tbs,
                'fid' => $fid,
                'z' => $tid
            ];
            curl_setopt($curl, CURLOPT_URL, 'http://tieba.baidu.com/mo/q/apubpost');
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post_data));
            return json_decode(curl_exec($curl), true);
        } else {
            // 客户端回贴
            $post_data = [
                'BDUSS' => $bduss,
                '_client_id' => 'wappc_136'.mt_rand(1000000000, 9999999999).'_'.mt_rand(100, 999),
                '_client_type' => $devices,
                '_client_version' => '6.5.2',
                '_phone_imei' => md5($bduss),
                'anonymous' => 0,
                'content' => $content,
                'fid' => $fid,
                'kw' => $tieba,
                'net_type' => 3,
                'tbs' => $tbs,
                'tid' => $tid,
                'title' => ''
            ];
            foreach ($post_data as $key => $value) {
                $sign .= "{$key}={$value}";
            }
            $post_data['sign'] = strtoupper(md5($sign.'tiebaclient!!!'));
            curl_setopt($curl, CURLOPT_URL, 'http://c.tieba.baidu.com/c/c/post/add');
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post_data));
            return json_decode(curl_exec($curl), true);
        }
    } else {
        // WAP楼中楼
        $post_data = [
            'co' => $content,
            'src' => 3,
            'word' => $tieba,
            'tbs' => $tbs,
            'tn' => 'baiduWiseSubmit',
            'fid' => $fid,
            'z' => $tid,
            'pid' => $pid
        ];
        curl_setopt($curl, CURLOPT_URL, 'http://tieba.baidu.com/mo/q/submit');
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post_data));
        return json_decode(curl_exec($curl), true);
    }
}

function get_tieba_fid($forum) {
    return json_decode(file_get_contents("http://tieba.baidu.com/f/commit/share/fnameShareApi?ie=utf-8&fname={$forum}"), true)['data']['fid'];
}

function get_tbs($bduss) {
    $curl = curl_init('http://tieba.baidu.com/dc/common/tbs');
    curl_setopt($curl, CURLOPT_COOKIE, "BDUSS={$bduss};");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    return json_decode(curl_exec($curl), true)['tbs'];
}

function is_content_match_regex($forum, $fid, $tid, $pid = null, $spid = null, $author, $content) {
    global $sql;
    global $bduss;
    global $content_regex;
    if ($sql -> query("SELECT COUNT(*) FROM tbmonitor_reply_replylog WHERE tid = {$tid}" . (empty($pid) ? null : " AND pid = {$pid}") . (empty($spid) ? null : " AND spid = {$spid}")) -> fetch_assoc()['COUNT(*)'] == 0) {
        foreach ($content_regex as $regex) {
            if (preg_match("/{$regex['regex']}/i", $content)) {
                $reply_content = empty($spid) ? $regex['reply'] : "回复 {$author} : {$regex['reply']}";
                $post_type = empty($pid) ? 'reply' : 'lzl';
                $post_type = mb_strlen($reply_content) > 140 ? 'reply' : 'lzl';
                $reply_content = $post_type == 'reply' ? preg_replace('/回复 (.*?) : /', '@${1} ', $reply_content) : $reply_content;
                switch ($post_type) {
                    case 'reply':
                        // 回贴客户端 0:WAP|1:iPhone|2:Android|3:WindowsPhone|4:Windows8UWP 默认随机
                        $reply_devices = 0;
                        // iPhone回贴秒吞
                        $reply_devices = $reply_devices == 1 ? rand(2, 4) : $reply_devices;
                        $reply_result = post_reply($bduss, get_tbs($bduss), $reply_devices, $forum, $fid, $tid, null, $reply_content);
                        break;
                    case 'lzl':
                        $reply_result = post_reply($bduss, get_tbs($bduss), 0, $forum, $fid, $tid, $pid, $reply_content);
                        break;
                }
                if (!empty($reply_result) && $reply_result[$reply_devices == 0 ? 'no' : 'error_code'] == 0) {
                    $sql -> query('INSERT INTO tbmonitor_reply_replylog (time, tid, pid, spid, content) VALUES ("'. date('Y-m-d H:i:s') . "\", {$tid}, " . (empty($pid) ? 'NULL' : $pid) . ', ' . (empty($spid) ? 'NULL' : $spid) . ", \"{$reply_content}\")");
                }
                return;
            }
        }
    }
}

foreach ($posts as $post) {
    $first_post_content = $sql -> query("SELECT content FROM tbmonitor_reply WHERE pid = {$post['first_post_id']}") -> fetch_assoc()['content'];
    is_content_match_regex($tieba, $tieba_fid, $post['tid'], null, null, $post['author'], strip_tags("{$post['title']}\r\n{$first_post_content}"));
}

foreach ($replys as $reply) {
    is_content_match_regex($tieba, $tieba_fid, $reply['tid'], $reply['pid'], null, $reply['author'], strip_tags($reply['content']));
}

foreach ($lzls as $lzl) {
    is_content_match_regex($tieba, $tieba_fid, $lzl['tid'], $lzl['pid'], $lzl['spid'], $lzl['author'], strip_tags($lzl['content']));
}