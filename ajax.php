<?php
ini_set('display_errors', 'On');
date_default_timezone_set('PRC');
$sql = new mysqli('127.0.0.1', 'n0099', 'iloven0099', 'n0099');

$_GET['type'] = $sql -> escape_string($_GET['type']);
$_GET['forum'] = $sql -> escape_string($_GET['forum']);
$_GET['post'] = $sql -> escape_string($_GET['post']);
$_GET['days'] = (int)$_GET['days'];
if ($_GET['post'] !== 'post' & $_GET['post'] !== 'reply' & $_GET['post'] !== 'lzl') { $_GET['post'] == null; }
$time_column_name = $_GET['post'] == 'reply' || $_GET['post'] == 'lzl' ? 'reply_time' : 'post_time';

switch ($_GET['type']) {
    case 'get_cron_time':
        echo json_encode($sql -> query("SELECT * FROM tbmonitor_time WHERE type = \"cron\" AND DATE_SUB(CURDATE(), INTERVAL {$_GET['days']} DAY) <= date(date)") -> fetch_all(MYSQLI_ASSOC));
        break;
    case 'get_post_count_by_hour':
        echo json_encode($sql -> query("SELECT EXTRACT(HOUR FROM {$time_column_name}) AS HOUR, COUNT(*) FROM tbmonitor_{$_GET['post']} WHERE forum = \"{$_GET['forum']}\" AND DATE_SUB(CURDATE(), INTERVAL {$_GET['days']} DAY) <= date({$time_column_name}) GROUP BY EXTRACT(HOUR FROM {$time_column_name})") -> fetch_all(MYSQLI_ASSOC));
        break;
    case 'get_post_count_by_day':
        echo json_encode($sql -> query("SELECT CAST({$time_column_name} AS DATE) AS DATE, COUNT(*) FROM tbmonitor_{$_GET['post']} WHERE forum = \"{$_GET['forum']}\" AND DATE_SUB(CURDATE(), INTERVAL {$_GET['days']} DAY) <= date({$time_column_name}) GROUP BY CAST({$time_column_name} AS DATE)") -> fetch_all(MYSQLI_ASSOC));
        break;
    default:
        die();
}