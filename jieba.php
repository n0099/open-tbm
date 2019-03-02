<?php
require 'core.php';
ini_set('memory_limit', '1024M');

require_once "jieba/src/vendor/multi-array/MultiArray.php";
require_once "jieba/src/vendor/multi-array/Factory/MultiArrayFactory.php";
require_once "jieba/src/class/Jieba.php";
require_once "jieba/src/class/Finalseg.php";
require_once "jieba/src/class/JiebaAnalyse.php";
use Fukuball\Jieba\Jieba;
use Fukuball\Jieba\Finalseg;
use Fukuball\Jieba\JiebaAnalyse;
Jieba::init(['mode'=>'test']);
Finalseg::init();
JiebaAnalyse::init();

$tieba = "模拟城市";
$sql_query_time = 'BETWEEN "' . date('Y-m-d', strtotime(date('Y-m-d') . '-30 day')) . '" AND "' . date('Y-m-d', strtotime(date('Y-m-d') . '+1 day')) . '"';
$posts = $sql -> query("SELECT * FROM tbmonitor_post WHERE forum=\"{$tieba}\" AND post_time {$sql_query_time}") -> fetch_all(MYSQLI_ASSOC);
$replys = $sql -> query("SELECT * FROM tbmonitor_reply WHERE forum=\"{$tieba}\" AND reply_time {$sql_query_time}") -> fetch_all(MYSQLI_ASSOC);
$lzls = $sql -> query("SELECT * FROM tbmonitor_lzl WHERE forum=\"{$tieba}\" AND reply_time {$sql_query_time} ") -> fetch_all(MYSQLI_ASSOC);

$data;

foreach ($posts as $post) {
    $data .= strip_tags($post['title']) . ' ';
}
foreach ($replys as $reply) {
    $data .= strip_tags($reply['content']) . ' ';
}
foreach ($lzls as $lzl) {
    $data .= strip_tags($lzl['content']) . ' ';
}

//var_dump($data);

echo '<br />';
$cut = Jieba::cut($data);
$count_values = array_count_values($cut);
$count = strlen($data);
arsort($count_values);
$biggest = $count_values[0];
foreach ($count_values as $key => $value) {
    $count_values[$key] = $value / $count;
}
print_r($count_values);

echo '<br />';
//$cipin = JiebaAnalyse::extractTags($data, 100);
print_r($cipin);
/*foreach ($cipin as $key => $value) {
    echo "{$key}<br />";
}*/