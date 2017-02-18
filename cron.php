<?php
ini_set('display_errors', 'On');
date_default_timezone_set('PRC');

function tieba_magic_time($time) {
    if (preg_match('/^\d{4}-\d{1,2}$/', $time)) {
        return $time.'-01 00:00:00';
    } elseif (preg_match('/^\d{1,2}-\d{1,2}$/', $time)) {
        return date('Y-m-d H:i:s', strtotime(date('Y') . "-{$time}"));
    } elseif (preg_match('/^\d{1,2}:\d{1,2}$/', $time)) {
        return date('Y-m-d') . " {$time}";
    }
}

$time = microtime(true);
$sql = new mysqli('127.0.0.1', 'n0099', 'iloven0099', 'n0099');
$forum = ['模拟城市', 'transportfever'];

foreach ($forum as $tieba) {
    // curl
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, "http://tieba.baidu.com/f?kw={$tieba}&ie=utf-8&pn=0&pagelets=frs-list%2Fpagelet%2Fthread");
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    // 解码解转义
    preg_match('/<script>Bigpipe.register\("frs-list\/pagelet\/thread_list", (.*),"parent/', $response, $regex_match);
    $explode = explode('<li class=" j_thread_list', htmlspecialchars_decode(json_decode($regex_match[1] . '}', true)['content']));
    // 话题贴
    preg_match('/<script>Bigpipe.register\("live\/pagelet\/live_thread", (.*),"parent/', $response, $regex_match);
    $topic = json_decode($regex_match[1] . '}', true);
    if (!empty($topic['content'])) { $explode['topic'] = htmlspecialchars_decode($topic['content']);}
    unset($explode[0]);
    foreach ($explode as $index => $post) {
        if ($index == 'topic') {
            // 话题贴id
            preg_match('/http:\/\/tieba.baidu.com\/p\/(\d*)/', $post, $regex_match);
            $post_data['id'] = $regex_match[1];
            // 话题贴标题
            preg_match('/<a href="http:\/\/tieba.baidu.com\/p\/\d*" target="_blank" title=".*">\s*(.*)<\/a>/', $post, $regex_match);
            $post_title = trim($regex_match[1]);
            // 话题贴发贴人
            preg_match('/<a title="" href="http:\/\/tieba.baidu.com\/home\/main\?un=.*&ie=utf-8&from=live" target="_blank">\s*(.*)<\/a>/', $post, $regex_match);
            $post_data['author_name'] = trim($regex_match[1]);
            // 话题贴回复数
            preg_match('/<span class="listReplyNum inlineBlock" id="interviewReply" title="\d*个回复">(\d*)<\/span>/', $post, $regex_match);
            $post_data['reply_num'] = $regex_match[1];
        } else {
            // 主题贴信息
            $post_data = json_decode(strstr(strstr($post, '{"'), '}', true) . '}', true);
            if (empty($post_data['is_good'])) { $post_data['is_good'] = 0; }
            if (empty($post_data['is_top'])) { $post_data['is_top'] = 0; }
            // 主题贴标题
            preg_match('/<a href="\/p\/\d*" title=".*" target="_blank" class="j_th_tit ">(.*)<\/a>/', $post, $regex_match);
            $post_title = $regex_match[1];
            // 主题贴发表时间
            preg_match('/<span class="pull-right is_show_create_time" title="创建时间">(.*)<\/span>/', $post, $regex_match);
            $post_time = tieba_magic_time($regex_match[1]);
            // 主题贴最后回复人
            preg_match('/<span class="tb_icon_author_rely j_replyer" title="最后回复人: (.*)">/', $post, $regex_match);
            $latest_replyer = $regex_match[1];
            // 主题贴最后回复时间
            preg_match('/<span class="threadlist_reply_date pull_right j_reply_data" title="最后回复时间">\r\n(.*)<\/span>/', $post, $regex_match);
            $latest_reply_time = trim($regex_match[1]);
            $latest_reply_time = empty($latest_reply_time) ? null : tieba_magic_time($latest_reply_time);
        }
        $post_sql = $sql -> query("SELECT reply_num, post_time, latest_replyer, latest_reply_time FROM tbmonitor_post WHERE tid = {$post_data['id']}");
        $post_sql_data = mysqli_fetch_assoc($post_sql);
        // 避免写入不完整发贴/最后回复时间
        $post_time = $post_sql_data['post_time'] > $post_time ? $post_sql_data['post_time'] : $post_time;
        $latest_reply_time = $post_sql_data['latest_reply_time'] > $latest_reply_time ? $post_sql_data['latest_reply_time'] : $latest_reply_time;
        // 判断主题贴是否有更新
        $is_post_update = $post_sql_data['reply_num'] != $post_data['reply_num'] || $post_sql_data['latest_replyer'] != $latest_replyer || strtotime($post_sql_data['latest_reply_time']) > strtotime($latest_reply_time);
        if ($index == 'topic' || ($post_sql -> num_rows == 0 || ($post_sql -> num_rows != 0 && $is_post_update))) {
            // 获取主题贴第一页回复
            curl_setopt($curl, CURLOPT_URL, "http://tieba.baidu.com/p/{$post_data['id']}?pn=1&ajax=1");
            $response = curl_exec($curl);
            // 获取主题贴回复页数
            preg_match('/共<span class="red">(\d*)<\/span>页/', $response, $regex_match);
            $reply_pages = $regex_match[1];
            // 遍历主题贴所有回复页
            for ($i = 1; $i <= $reply_pages; $i++) {
                if ($i != 1) {
                    curl_setopt($curl, CURLOPT_URL, "http://tieba.baidu.com/p/{$post_data['id']}?pn={$i}&ajax=1");
                    $response = curl_exec($curl);
                }
                $explode = explode('<div class="l_post l_post_bright j_l_post clearfix  "  data-field=\'', $response);
                foreach ($explode as $reply) {
                    // 回复信息
                    $reply_data = json_decode(htmlspecialchars_decode(strstr($reply, "' >", true)), true);
                    if (empty($reply_data['content']['lzl_num'])) { $reply_data['content']['lzl_num'] = 0; }
                    // 回复内容
                    preg_match('/<cc>\s*<div id="post_content_\d*" class="d_post_content j_d_post_content ">(.*?)<\/div><br>\s*<\/cc>/', $reply, $regex_match);
                    $reply_content = trim($regex_match[1]);
                    // 回复时间
                    preg_match('/<span class="tail-info">(\d{4}-\d{2}-\d{2} \d{2}:\d{2})<\/span>/', $reply, $regex_match);
                    $reply_time = $regex_match[1];
                    // 判断楼中楼是否有更新
                    $reply_sql = $sql -> query("SELECT lzl_num FROM tbmonitor_reply WHERE pid = {$reply_data['content']['post_id']}");
                    $reply_sql_data = mysqli_fetch_assoc($reply_sql);
                    if (($reply_data['content']['post_no'] != 1 && $reply_sql -> num_rows == 0) || ($reply_sql -> num_rows != 0 && ($reply_sql_data['lzl_num'] != $reply_data['content']['comment_num']))) {
                        curl_setopt($curl, CURLOPT_URL, "http://tieba.baidu.com/p/comment?tid={$post_data['id']}&pid={$reply_data['content']['post_id']}&pn=1");
                        $response = curl_exec($curl);
                        preg_match('/<a href="#(\d*)">尾页<\/a>/', $response, $regex_match);
                        $lzl_pages = empty($regex_match) ? 1 : $regex_match[1];
                        for ($j = 1; $j <= $lzl_pages; $j++) {
                            if ($j != 1) {
                                curl_setopt($curl, CURLOPT_URL, "http://tieba.baidu.com/p/comment?tid={$post_data['id']}&pid={$reply_data['content']['post_id']}&pn={$j}");
                                $response = curl_exec($curl);
                            }
                            $explode = explode('<li class="lzl_single_post j_lzl_s_p ', $response);
                            foreach ($explode as $lzl) {
                                // 楼中楼信息
                                preg_match('/data-field=\'({.*?})/', $lzl, $regex_match);
                                $lzl_date = json_decode(htmlspecialchars_decode($regex_match[1]), true);
                                // 楼中楼内容
                                preg_match('/<span class="lzl_content_main">(.*?)<\/span>/', $lzl, $regex_match);
                                $lzl_content = trim($regex_match[1]);
                                // 楼中楼回复时间
                                preg_match('/<span class="lzl_time">(\d{4}-\d{1,2}-\d{1,2} \d{2}:\d{2})<\/span>/', $lzl, $regex_match);
                                $lzl_reply_time = $regex_match[1];
                                // 楼中楼数据库
                                $query = sprintf("INSERT INTO tbmonitor_lzl (forum, tid, pid, spid, author, content, reply_time) VALUES (\"{$tieba}\", {$post_data['id']}, {$reply_data['content']['post_id']}, {$lzl_date['spid']}, \"%s\", \"%s\", \"{$lzl_reply_time}\")", $sql -> escape_string($lzl_date['user_name']), $sql -> escape_string($lzl_content));
                                $sql -> query($query);
                            }
                        }
                    }
                    // 回复贴数据库
                    $query = sprintf("INSERT INTO tbmonitor_reply (forum, tid, pid, author, content, floor, lzl_num, reply_time) VALUES (\"{$tieba}\", {$post_data['id']}, {$reply_data['content']['post_id']}, \"%s\", \"%s\", {$reply_data['content']['post_no']}, {$reply_data['content']['comment_num']}, \"{$reply_time}\") ON DUPLICATE KEY UPDATE lzl_num = {$reply_data['content']['comment_num']}", $sql -> escape_string($reply_data['author']['user_name']), $sql -> escape_string($reply_content));
                    $sql -> query($query);
                }
            }
        }
        // 主题贴数据库
        if ($index == 'topic') {
            $query = sprintf("INSERT INTO tbmonitor_post (forum, tid, title, author, reply_num) VALUES (\"{$tieba}\", {$post_data['id']}, \"%s\", \"%s\", {$post_data['reply_num']}) ON DUPLICATE KEY UPDATE reply_num={$post_data['reply_num']}", $sql -> escape_string($post_title), $sql -> escape_string($post_data['author_name']));
        } else {
            $query = sprintf("INSERT INTO tbmonitor_post (forum, tid, first_post_id, is_top, is_good, title, author, reply_num, post_time, latest_replyer, latest_reply_time) VALUES (\"{$tieba}\", {$post_data['id']}, {$post_data['first_post_id']}, {$post_data['is_top']}, {$post_data['is_good']}, \"%s\", \"%s\", {$post_data['reply_num']}, \"{$post_time}\", \"%s\", %s) ON DUPLICATE KEY UPDATE first_post_id = {$post_data['first_post_id']}, is_top = {$post_data['is_top']}, is_good = {$post_data['is_good']}, reply_num = {$post_data['reply_num']}, post_time = \"{$post_time}\", latest_replyer = \"%s\", latest_reply_time = %s", $sql -> escape_string($post_title), $sql -> escape_string($post_data['author_name']), $sql -> escape_string($latest_replyer), empty($latest_reply_time) ? 'null' : "\"{$latest_reply_time}\"", $sql -> escape_string($latest_replyer), empty($latest_reply_time) ? 'null' : "\"{$latest_reply_time}\"");
        }
        $sql -> query($query);
    }
}

echo '耗时' . round(microtime(true) - $time, 10) . '秒';