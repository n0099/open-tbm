<?php
require 'core.php';
header('Content-Type: application/json');

$_GET['type'] = $sql -> escape_string($_GET['type']);
$_GET['forum'] = $sql -> escape_string($_GET['forum']);
$_GET['post'] = $sql -> escape_string($_GET['post']);
$_GET['days'] = (int)$_GET['days'];
$_GET['months'] = (int)$_GET['months'];
if ($_GET['post'] !== 'post' & $_GET['post'] !== 'reply' & $_GET['post'] !== 'lzl') { $_GET['post'] == null; }
$time_column_name = $_GET['post'] == 'reply' || $_GET['post'] == 'lzl' ? 'reply_time' : 'post_time';

switch ($_GET['type']) {
    case 'get_forums':
        echo json_encode($sql -> query('SELECT DISTINCT forum FROM tbmonitor_post') -> fetch_all(MYSQLI_ASSOC));
        break;
    case 'get_cron_time':
        echo json_encode($sql -> query("SELECT * FROM tbmonitor_time WHERE type = \"cron\" AND end_time BETWEEN DATE_SUB(CURDATE(), INTERVAL {$_GET['days']} DAY) AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)") -> fetch_all(MYSQLI_ASSOC));
        break;
    case 'get_post_count_by_hour':
        echo json_encode($sql -> query("SELECT EXTRACT(HOUR FROM {$time_column_name}) AS HOUR, COUNT(*) FROM tbmonitor_{$_GET['post']} WHERE forum = \"{$_GET['forum']}\" AND {$time_column_name} BETWEEN DATE_SUB(CURDATE(), INTERVAL {$_GET['days']} DAY) AND DATE_ADD(CURDATE(), INTERVAL 1 DAY) GROUP BY EXTRACT(HOUR FROM {$time_column_name})") -> fetch_all(MYSQLI_ASSOC));
        break;
    case 'get_post_count_by_day':
        echo json_encode($sql -> query("SELECT DATE({$time_column_name}) AS DATE, COUNT(*) FROM tbmonitor_{$_GET['post']} WHERE forum = \"{$_GET['forum']}\" AND {$time_column_name} BETWEEN DATE_SUB(CURDATE(), INTERVAL {$_GET['days']} DAY) AND DATE_ADD(CURDATE(), INTERVAL 1 DAY) GROUP BY DATE({$time_column_name}) ORDER BY DATE DESC") -> fetch_all(MYSQLI_ASSOC));
        break;
    case 'get_post_count_by_month':
        echo json_encode($sql -> query("SELECT EXTRACT(YEAR_MONTH FROM {$time_column_name}) AS MONTH, COUNT(*) FROM tbmonitor_{$_GET['post']} WHERE forum = \"{$_GET['forum']}\" AND {$time_column_name} BETWEEN DATE_SUB(CURDATE(), INTERVAL {$_GET['months']} MONTH) AND DATE_ADD(CURDATE(), INTERVAL 1 DAY) GROUP BY EXTRACT(YEAR_MONTH FROM {$time_column_name}) ORDER BY MONTH DESC") -> fetch_all(MYSQLI_ASSOC));
        break;
    default:
        die();
}