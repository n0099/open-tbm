<?php
ini_set('display_errors', 'On');

DB::$host = $_ENV['DATABASE_HOST'];
DB::$user = $_ENV['DATABASE_USER'];
DB::$password = $_ENV['DATABASE_PASS'];
DB::$dbName = $_ENV['DATABASE_NAME'];

function tieba_magic_time($time) {
    if (preg_match('/^\d{1,2}-\d{1,2}$/', $time)) {
        return date('Y-m-d H:i:s', strtotime(date('Y') . "-{$time}"));
    } elseif (preg_match('/^\d{1,2}:\d{1,2}$/', $time)) {
        return date('Y-m-d') . " {$time}";
    }
}

$t1 = microtime(true);

$forum = ['模拟城市'];
foreach ($forum as $tieba) {
    // curl
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, "http://tieba.baidu.com/f?kw={$tieba}&ie=utf-8&pn=0&pagelets=frs-list%2Fpagelet%2Fthread");
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);

    $t2 = microtime(true);
    echo 'curl耗时' . round($t2 - $t1, 10) . '秒';
    $t1 = microtime(true);

    $sql = new mysqli($_ENV['DATABASE_HOST'], $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASS'], $_ENV['DATABASE_NAME']);
    // 解码解转义
    preg_match('/<script>Bigpipe.register\("frs-list\/pagelet\/thread_list".*,"parent/', $response, $regex_result);
    $replace = ['<script>Bigpipe.register("frs-list/pagelet/thread_list", ' => '', ',"parent' => ''];
    $return = htmlspecialchars_decode(json_decode(strtr($regex_result[0], $replace) . '}', true)['content']);
    $explode = explode('<li class=" j_thread_list', $return);

    foreach ($explode as $post) {
        // 主题帖信息
        $post_data = json_decode(strstr(strstr($post, '{"'), '}', true) . '}', true);
        if (empty($post_data['is_good'])) { $post_data['is_good'] = 0; }
        if (empty($post_data['is_top'])) { $post_data['is_top'] = 0; }
        // 主题帖标题
        preg_match('/<a href="\/p\/\d*" title=".*" target="_blank" class="j_th_tit ">.*<\/a>/', $post, $regex_result);
        $post_title = substr(strstr(strstr($regex_result[0], '>'), '</a', true), 1);
        // 主题帖发表时间
        preg_match('/<span class="pull-right is_show_create_time" title="创建时间">.*<\/span>/', $post, $regex_result);
        $post_time = substr(strstr(strstr($regex_result[0], '>'), '</span', true), 1);
        $post_time = tieba_magic_time($post_time);
        // 主题帖最后回复人
        preg_match('/<span class="tb_icon_author_rely j_replyer" title="最后回复人: .*">/', $post, $regex_result);
        $latest_replyer = substr(strstr(strstr($regex_result[0], '最后回复人: '), '">', true), 17);
        // 主题帖最后回复时间
        preg_match('/<span class="threadlist_reply_date pull_right j_reply_data" title="最后回复时间">\r\n.*<\/span>/', $post, $regex_result);
        $latest_reply_time = trim(substr(strstr(strstr($regex_result[0], '>'), '</span', true), 1));
        $latest_reply_time = empty($latest_reply_time) ? null : tieba_magic_time($latest_reply_time);
        // 判断主题帖是否有更新
        $post_sql = $sql -> query("SELECT reply_num, latest_replyer, latest_reply_time FROM tbmonitor_post WHERE tid={$post_data['id']}");
        $post_sql_data = mysqli_fetch_array($post_sql);
        if ($post_sql -> num_rows == 0 || ($post_sql -> num_rows != 0 && ($post_sql_data['reply_num'] != $post_data['reply_num'] || $post_sql_data['latest_replyer'] != $latest_replyer || strtotime($post_sql_data['latest_reply_time']) > strtotime($latest_reply_time)))) {
            // 获取主题帖第一页回复
            curl_setopt($curl, CURLOPT_URL, "http://tieba.baidu.com/p/{$post_data['id']}?pn=1&ajax=1");
            $response = curl_exec($curl);
            // 获取主题帖回复页数
            preg_match('/共<span class="red">\d*<\/span>页/', $response, $regex_result);
            $reply_pages = substr(strstr(strstr($regex_result[0], '>'), '</span', true), 1);
            // 遍历主题帖所有回复页
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
                    preg_match('/<cc>\s*<div id="post_content_\d*" class="d_post_content j_d_post_content ">.*?<\/div><br>\s*<\/cc>/', $reply, $regex_result);
                    $reply_content = trim(substr(strstr(strstr($regex_result[0], ' ">'), '</div><br>', true), 3));
                    // 回复时间
                    preg_match('/<span class="tail-info">\d{4}-\d{2}-\d{2} \d{2}:\d{2}<\/span>/', $reply, $regex_result);
                    $reply_time = substr(strstr(strstr($regex_result[0], '>'), '</span', true), 1);
                    // 判断楼中楼是否有更新
                    $reply_sql = $sql -> query("SELECT lzl_num FROM tbmonitor_reply WHERE pid={$reply_data['content']['post_id']}");
                    $reply_sql_data = mysqli_fetch_array($reply_sql);
                    if (($reply_data['content']['post_no'] != 1 && $reply_sql -> num_rows == 0) || ($reply_sql -> num_rows != 0 && ($reply_sql_data['lzl_num'] != $reply_data['content']['comment_num']))) {
                        curl_setopt($curl, CURLOPT_URL, "http://tieba.baidu.com/p/comment?tid={$post_data['id']}&pid={$reply_data['content']['post_id']}&pn=1");
                        $response = curl_exec($curl);
                        preg_match('/<a href="#\d*">尾页<\/a>/', $response, $regex_result);
                        $lzl_pages = empty($regex_result) ? 1 :substr(strstr(strstr($regex_result[0], 'href="#'), '">', true), 7);
                        for ($j = 1; $j <= $lzl_pages; $j++) {
                            if ($j != 1) {
                                curl_setopt($curl, CURLOPT_URL, "http://tieba.baidu.com/p/comment?tid={$post_data['id']}&pid={$reply_data['content']['post_id']}&pn={$j}");
                                $response = curl_exec($curl);
                            }
                            $explode = explode('<li class="lzl_single_post j_lzl_s_p ', $response);
                            foreach ($explode as $lzl) {
                                // 楼中楼信息
                                preg_match('/data-field=\'{.*?}/', $lzl, $regex_result);
                                $lzl_date = json_decode(htmlspecialchars_decode(substr($regex_result[0], 12)), true);
                                // 楼中楼内容
                                preg_match('/<span class="lzl_content_main">.*?<\/span>/', $lzl, $regex_result);
                                $lzl_content = trim(substr(strstr(strstr($regex_result[0], '>'), '</span', true), 1));
                                // 楼中楼回复时间
                                preg_match('/<span class="lzl_time">\d{4}-\d{1,2}-\d{1,2} \d{2}:\d{2}<\/span>/', $lzl, $regex_result);
                                $lzl_reply_time = substr(strstr(strstr($regex_result[0], '>'), '</span', true), 1);
                                // 楼中楼数据库
                                $query = sprintf("INSERT INTO tbmonitor_lzl (forum, tid, pid, spid, author, content, reply_time) VALUES (\"{$tieba}\", {$post_data['id']}, {$reply_data['content']['post_id']}, {$lzl_date['spid']}, \"%s\", \"%s\", \"{$lzl_reply_time}\")", $sql -> escape_string($lzl_date['user_name']), $sql -> escape_string($lzl_content));
                                $sql -> query($query);
                            }
                        }
                    }
                    // 回复帖数据库
                    $query = sprintf("INSERT INTO tbmonitor_reply (forum, tid, pid, author, content, floor, lzl_num, reply_time) VALUES (\"{$tieba}\", {$post_data['id']}, {$reply_data['content']['post_id']}, \"%s\", \"%s\", {$reply_data['content']['post_no']}, {$reply_data['content']['comment_num']}, \"{$reply_time}\") ON DUPLICATE KEY UPDATE lzl_num={$reply_data['content']['comment_num']}", $sql -> escape_string($reply_data['author']['user_name']), $sql -> escape_string($reply_content));
                    $sql -> query($query);
                }
            }
        }
        // 主题帖数据库
        $query = sprintf("INSERT INTO tbmonitor_post (forum, tid, first_post_id, is_top, is_good, title, author, reply_num, post_time, latest_replyer, latest_reply_time) VALUES (\"{$tieba}\", {$post_data['id']}, {$post_data['first_post_id']}, {$post_data['is_top']}, {$post_data['is_good']}, \"%s\", \"%s\", {$post_data['reply_num']}, \"{$post_time}\", \"{$latest_replyer}\", %s) ON DUPLICATE KEY UPDATE is_top={$post_data['is_top']}, is_good={$post_data['is_good']}, reply_num={$post_data['reply_num']}, latest_replyer=\"{$latest_replyer}\", latest_reply_time=%s", $sql -> escape_string($post_title), $sql -> escape_string($post_data['author_name']), empty($latest_reply_time) ? 'null' : "\"{$latest_reply_time}\"", empty($latest_reply_time) ? 'null' : "\"{$latest_reply_time}\"");
        $sql -> query($query);
    }
}

$t2 = microtime(true);
echo '其他耗时' . round($t2 - $t1, 10) . '秒';