<?php
ini_set('display_errors', 'On');
date_default_timezone_set('PRC');
$sql = new mysqli('127.0.0.1', 'n0099', 'iloven0099', 'n0099');

$_GET['type'] = $sql -> escape_string($_GET['type']);
$_GET['days'] = (int)$_GET['days'];

switch ($_GET['type']) {
    case 'get_cron_time':
        echo json_encode($sql -> query("SELECT * FROM tbmonitor_time WHERE type = \"cron\" AND DATE_SUB(CURDATE(), INTERVAL {$_GET['days']} DAY) <= date(date)") -> fetch_all(MYSQLI_ASSOC));
        break;
    default:
        die();
        break;
}
?>