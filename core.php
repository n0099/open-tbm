<?php
ini_set('display_errors', 'On');
date_default_timezone_set('PRC');
$sql = new mysqli('localhost', '', '', '');
$sql -> query("SET collation_connection = utf8mb4_unicode_ci");

function get_cron_time($minutes, $get_value) {
    $value = round($GLOBALS['sql'] -> query("SELECT AVG(time) FROM tbmonitor_time WHERE end_time >= DATE_ADD(NOW(), INTERVAL -{$minutes} MINUTE)") -> fetch_all(MYSQLI_ASSOC)[0]['AVG(time)'], 2);
    if ($get_value == false) { return empty($value) ? '未知' : $value; }
    switch ($value) {
        case $value >= 60:
            return '低';
        case $value >= 30:
            return '中';
        case $value < 30:
            return '高';
        default:
            return '未知';
    }
}

function get_post_portal($tid, $pid = null, $spid = null) {
    $return = "http://tieba.baidu.com/p/{$tid}";
    $return = $pid != null & $spid == null ? "{$return}?pid={$pid}#{$pid}" : $return;
    $return = $pid != null & $spid != null ? "{$return}?pid={$pid}&cid={$spid}#{$spid}" : $return;
    return $return;
}

function get_user_space($username) {
    return "http://tieba.baidu.com/home/main?un={$username}";
}