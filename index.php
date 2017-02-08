<?php
require 'init.php';
ini_set('display_errors', 'On');
$t1 = microtime(true);

// curl
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, "http://tieba.baidu.com/f?kw=trainfever&ie=utf-8&pn=0&pagelets=frs-list%2Fpagelet%2Fthread");
curl_setopt($curl, CURLOPT_HEADER, false);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($curl);

$t2 = microtime(true);
echo 'curl耗时'.round($t2-$t1,10).'秒';
$t1 = microtime(true);

DB::$host='127.0.0.1';
DB::$dbName=$_ENV["DATABASE_NAME"];
DB::$user=$_ENV["DATABASE_USER"];
DB::$password=$_ENV["DATABASE_PASS"];
$sql = new mysqli('127.0.0.1', 'n0099', 'iloven0099', 'n0099');

// 解码解转义
preg_match('/\<script\>Bigpipe.register\("frs-list\/pagelet\/thread_list".*,"parent/', $response, $regex_result);
$replace = ['<script>Bigpipe.register("frs-list/pagelet/thread_list", ' => '', ',"parent' => ''];
$return = htmlspecialchars_decode(json_decode(strtr($regex_result[0], $replace).'}', true)['content']);

$explode = explode('<li class=" j_thread_list', $return);
foreach ($explode as $post) {
  // 主题帖信息
  $post_data = json_decode(strstr(strstr($post, '{"'), '}', true).'}', true);
  if ($post_data['is_good'] == '') {$post_data['is_good'] = 0;}
  if ($post_data['is_top'] == '') {$post_data['is_top'] = 0;}
  // 主题帖标题
  preg_match('/<a href="\/p\/\d*" title=".*" target="_blank" class="j_th_tit ">.*<\/a>/', $post, $regex_result);
  $post_title = substr(strstr(strstr($regex_result[0] ,'>'), '</a', true), 1);
  // 主题帖发表时间
  preg_match('/<span class="pull-right is_show_create_time" title="创建时间">.*<\/span>/', $post, $regex_result);
  $post_time = substr(strstr(strstr($regex_result[0],'>'), '</span', true), 1);
  if (preg_match('/^\d{1,2}-\d{1,2}$/', $post_time)) {$post_time = date('Y').'-'.$post_time;}
  $post_time = date('Y-m-d H:i:s', strtotime($post_time));
  // 主题帖最后回复人
  preg_match('/<span class="tb_icon_author_rely j_replyer" title="最后回复人: .*">/', $post, $regex_result);
  $latest_replyer = substr(strstr(strstr($regex_result[0],'最后回复人: '), '">', true), 17);
  // 主题帖最后回复时间
  preg_match('/<span class="threadlist_reply_date pull_right j_reply_data" title="最后回复时间">\r\n.*<\/span>/', $post, $regex_result);
  $latest_reply_time = trim(substr(strstr(strstr($regex_result[0],'>'), '</span', true), 1));
  //if ($latest_reply_time != '') {
    if (preg_match('/^\d{1,2}-\d{1,2}$/', $latest_reply_time)) {$latest_reply_time = date('Y').'-'.$latest_reply_time;}
    $latest_reply_time = date('Y-m-d H:i:s', strtotime($latest_reply_time));
  //};
  // 主题帖数据库
    DB::insertUpdate('tbmonitor_post', array(
        'tid' => $post_data['id'],
        'title' => $post_title,
        'is_good' => $post_data['is_good'],
        'is_top' => $post_data['is_top'],
        'author' => $post_data['author_name'],
        'first_post_id' => $post_data['first_post_id'],
        'reply_num' => $post_data['reply_num'],
        'post_time' => $post_time,
        'latest_replyer' => $latest_replyer,
        'latest_reply_time' => $latest_reply_time
    ), array (
        'is_good' => $post_data['is_good'],
        'is_top' => $post_data['is_top'],
        'reply_num' => $post_data['reply_num'],
        'latest_replyer' => $latest_replyer,
        'latest_reply_time' => $latest_reply_time
    ));

    $results = DB::query("SELECT * FROM tbmonitor_post WHERE tid={$post_data['id']}");
    foreach ($results as $row) {
        if ($row['reply_num'] != $post_data['reply_num'] || $row['latest_replyer'] != $latest_replyer || $row['latest_reply_time'] != $latest_reply_time) {
  // 判断主题帖是否有更新
  /*$postsql = $sql -> query("SELECT * FROM tbmonitor_post WHERE tid={$post_data['id']}");
  $postsqldata = mysqli_fetch_array($postsql);
  if ($postsql -> num_rows != 0 && ($postsqldata['reply_num'] != $post_data['reply_num'] || $postsqldata['latest_replyer'] != $latest_replyer || $postsqldata['latest_reply_time'] != $latest_reply_time)) {
  */
    // 获取主题帖第一页回复
    curl_setopt($curl, CURLOPT_URL, "http://tieba.baidu.com/p/{$post_data['id']}?pn=1&ajax=1");
    $response = curl_exec($curl);
    // 获取主题帖回复页数
    preg_match('/共<span class="red">\d*<\/span>页/', $response, $regex_result);
    $reply_pages = substr(strstr(strstr($regex_result[0],'>'), '</span', true), 1);
    // 遍历主题帖所有回复页
    for ($i = 1; $i <= $reply_pages; $i++) {
      if ($i != 1) {
        curl_setopt($curl, CURLOPT_URL, "http://tieba.baidu.com/p/{$post_data['id']}?pn={$i}&ajax=1");
        $response = curl_exec($curl);
      }
      $explode = explode('<div class="l_post l_post_bright j_l_post clearfix  "  ', $response);
      foreach ($explode as $reply) {
        // 回复信息
        $reply_data = json_decode(htmlspecialchars_decode(substr(strstr($reply, "' >", true), 12)), true);
        // 回复内容
        preg_match('/<div id="post_content_\d*" class="d_post_content j_d_post_content ">.*?<\/div>/', $reply, $regex_result);
        $reply_content = trim(substr(strstr(strstr($regex_result[0],'>'), '</div', true), 1)); 
        // 回复时间
        preg_match('/<span class="tail-info">\d{4}-\d{2}-\d{2} \d{2}:\d{2}<\/span>/', $reply, $regex_result);
        $reply_time = substr(strstr(strstr($regex_result[0],'>'), '</span', true), 1);
        // 回复帖数据库
          DB::insertUpdate('tbmonitor_post', array(
              'tid' => $post_data['id'],
              'title' => $post_title,
              'is_good' => $post_data['is_good'],
              'is_top' => $post_data['is_top'],
              'author' => $post_data['author_name'],
              'first_post_id' => $post_data['first_post_id'],
              'reply_num' => $post_data['reply_num'],
              'post_time' => $post_time,
              'latest_replyer' => $latest_replyer,
              'latest_reply_time' => $latest_reply_time
          ), array (
              'is_good' => $post_data['is_good'],
              'is_top' => $post_data['is_top'],
              'reply_num' => $post_data['reply_num'],
              'latest_replyer' => $latest_replyer,
              'latest_reply_time' => $latest_reply_time
          ));

        // 判断楼中楼是否有更新
        //楼中楼"http://tieba.baidu.com/p/comment?tid={$post_data['id']}&pid={$reply_data['post_id']}&pn=1"
      }
    }
  }
    }
}

$t2 = microtime(true);
echo '其他耗时'.round($t2-$t1,10).'秒';
?>