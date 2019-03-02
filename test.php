<?php

/**
 * 得到数组的标准差
 * @param unknown type $avg
 * @param Array $list
 * @param Boolen $isSwatch
 * @return unknown type
 */
function getVariance($avg, $list, $isSwatch  = FALSE) {
    $arrayCount = count($list);
    if($arrayCount == 1 && $isSwatch == TRUE){
        return FALSE;
    }elseif($arrayCount > 0 ){
        $total_var = 0;
        foreach ($list as $lv)
            $total_var += pow(($lv - $avg), 2);
        if($arrayCount == 1 && $isSwatch == TRUE)
            return FALSE;
        return $isSwatch?sqrt($total_var / (count($list) - 1 )):sqrt($total_var / count($list));
    }
    else
        return FALSE;
}

$sql = new mysqli('127.0.0.1', 'n0099', 'iloven0099', 'n0099');
$sql -> query("SET collation_connection = utf8mb4_unicode_ci");
$replies = $sql ->query("SELECT content FROM tbmonitor_reply WHERE forum = \"模拟城市\" AND content REGEXP '<img.* size=\d*'")->fetch_all(MYSQLI_ASSOC);
$pics = [];
$pics_count = 0;
foreach ($replies as $reply) {
    preg_match_all('/<img.*?size="(\d*?)"/', $reply['content'], $preg_match);
    $preg_match ? null : $pics_count += 1;
    $pics = array_merge($pics, $preg_match[1]);
}
print_r(json_encode($pics));
var_dump($pics_count);
/*
$pics_average = array_sum($pics) / count($pics);
var_dump($pics_average);
$variance = getVariance($pics_average, $pics, true);
var_dump($variance);
asort($pics);
$pics_pro = [];
foreach ($pics as $pic_size) {
    if ($pic_size < $variance) {
        $pics_pro['<1'] += 1;
        continue;
    } elseif ($pic_size < $variance * 2) {
        $pics_pro['<2'] += 1;
        continue;
    } elseif ($pic_size < $variance * 3) {
        $pics_pro['<3'] += 1;
        continue;
    } elseif ($pic_size < $variance * 4) {
        $pics_pro['<4'] += 1;
        continue;
    } elseif ($pic_size < $variance * 5) {
        $pics_pro['<5'] += 1;
        continue;
    }
    if ($pic_size > $variance) {
        $pics_pro['>1'] += 1;
        continue;
    } elseif ($pic_size > $variance * 2) {
        $pics_pro['>2'] += 1;
        continue;
    } elseif ($pic_size > $variance * 3) {
        $pics_pro['>3'] += 1;
        continue;
    } elseif ($pic_size > $variance * 4) {
        $pics_pro['>4'] += 1;
        continue;
    } elseif ($pic_size > $variance * 5) {
        $pics_pro['>5'] += 1;
        continue;
    }
}
print_r($pics_pro);
*/
