<?php
ini_set('display_errors', 'On');
date_default_timezone_set('PRC');
$time = microtime(true);
$sql = new mysqli('127.0.0.1', 'n0099', 'iloven0099', 'n0099');

$_GET['pn'] = (int)$_GET['pn'];
$_GET['type'] = empty($_GET['type']) ? ['post', 'reply', 'lzl'] : $_GET['type'];
$_GET['forum'] = $sql -> escape_string($_GET['forum']);
$_GET['tid'] = (int)$_GET['tid'];
$_GET['author'] = $sql -> escape_string($_GET['author']);
$_GET['start_date'] = empty($_GET['start_date']) ? null : date('Y-m-d', strtotime($_GET['start_date']));
$_GET['end_date'] = empty($_GET['end_date']) ? null : date('Y-m-d', strtotime($_GET['end_date']));
if (empty($_GET['start_date']) || empty($_GET['end_date'])) {
    $_GET['start_date'] = null;
    $_GET['end_date'] = null;
}

function get_post_portal($tid, $pid = null, $spid = null) {
    $return = "http://tieba.baidu.com/p/{$tid}";
    $return = $pid != null & $spid == null ? "{$return}?pid={$pid}#{$pid}" : $return;
    $return = $pid != null & $spid != null ? "{$return}?pid={$pid}&cid={$spid}#{$spid}" : $return;
    return $return;
}

function get_user_space($username) {
    return "http://tieba.baidu.com/home/main?un={$username}&ie=utf-8";
}

function get_url_arguments($pn = null, $type = null, $forum = null, $tid = null, $author = null, $start_date = null) {
    $arguments = [
        'pn' => null,
        'type' => null,
        'forum' => null,
        'tid'=> null,
        'author' => null,
        'start_date' => null,
        'end_date' => null
    ];
    foreach ($arguments as $arg_name => &$arg_value) {
        $arg_value = $$arg_name === null & !empty($_GET[$arg_name]) ? $_GET[$arg_name] : $$arg_name;
        $arg_value = $arg_value === null ? null : "{$arg_name}={$arg_value}";
        if ($arg_value === null) { unset($arguments[$arg_name]); }
    }
    unset($arg_value);
    if (!empty($_GET['type'])) {
        foreach ($_GET['type'] as $type) {
            $types[] = "type[]={$type}";
        }
        $arguments['type'] = implode('&', $types);
    }
    return 'https://n0099.cf/tbm/?' . implode('&', $arguments);
}

$sql_limit = 'LIMIT ' . ($_GET['pn'] == 0 ? 0 : $_GET['pn'] * 10) . ', 10';
/*if (empty($_GET['type']) & empty($_GET['forum']) & empty($_GET['tid']) & empty($_GET['author']) & empty($_GET['start_date']) & empty($_GET['end_date'])) {
    /*$sql_count = $sql -> query("SELECT COUNT(*) FROM tbmonitor_post UNION ALL SELECT COUNT(*) FROM tbmonitor_reply WHERE floor != 1 UNION ALL SELECT COUNT(*) FROM tbmonitor_lzl") -> fetch_all(MYSQLI_NUM);
    $sql_posts = "SELECT * FROM tbmonitor_post ORDER BY post_time DESC {$sql_limit}";
    $sql_replies = "SELECT * FROM tbmonitor_reply WHERE floor != 1 ORDER BY reply_time DESC {$sql_limit}";
    $sql_lzl = "SELECT * FROM tbmonitor_lzl ORDER BY reply_time DESC {$sql_limit}";
} else {
}*/
$sql_conditions = [
    'forum' => !empty($_GET['forum']) ? "forum = \"{$_GET['forum']}\"" : null,
    'tid' => !empty($_GET['tid']) ? "tid = {$_GET['tid']}" : null,
    'author' => !empty($_GET['author']) ? "author = \"{$_GET['author']}\"" : null,
    'date' => !empty($_GET['start_date']) & !empty($_GET['end_date']) ? "post_time BETWEEN \"{$_GET['start_date']}\" AND \"{$_GET['end_date']}\"" : null
];
foreach($sql_conditions as $condition => $value) {
    if (empty($value)) { unset($sql_conditions[$condition]); }
}
$sql_condition = empty($sql_conditions) ? null : 'WHERE ' . implode(' AND ', $sql_conditions);
$sql_order_by = 'ORDER BY post_time DESC';

if (in_array('post', $_GET['type'])) {
    $sql_count[] = "SELECT COUNT(*) FROM tbmonitor_post {$sql_condition}";
    $sql_posts = "SELECT * FROM tbmonitor_post {$sql_condition} {$sql_order_by} {$sql_limit}";
}
$sql_condition = str_replace('post_time', 'reply_time', $sql_condition);
$sql_order_by = 'ORDER BY reply_time DESC';
if (in_array('reply', $_GET['type'])) {
    $sql_where_floor = empty($sql_condition) ? 'WHERE floor != 1' : "{$sql_condition} AND floor != 1";
    $sql_count[] = "SELECT COUNT(*) FROM tbmonitor_reply {$sql_where_floor}";
    $sql_replies = "SELECT * FROM tbmonitor_reply {$sql_where_floor} {$sql_order_by} {$sql_limit}";
}
if (in_array('lzl', $_GET['type'])) {
    $sql_count[] = "SELECT COUNT(*) FROM tbmonitor_lzl {$sql_condition}";
    $sql_lzl = "SELECT * FROM tbmonitor_lzl {$sql_condition} {$sql_order_by} {$sql_limit}";
}
$sql_count = $sql -> query(implode(' UNION ALL ', $sql_count)) -> fetch_all(MYSQLI_NUM);

$max_page_num = intval(max($sql_count)[0] / 10);
$sql_results = [
    'posts' => empty($sql_posts) ? null : $sql -> query($sql_posts),
    'replies' => empty($sql_replies) ? null : $sql -> query($sql_replies),
    'lzl' => empty($sql_lzl) ? null : $sql -> query($sql_lzl)
];
foreach($sql_results as $type => $query) {
    if (empty($query)) { unset($sql_results[$type]); }
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>贴吧云监控</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link href="https://cdn.bootcss.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.bootcss.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" />
        <style>
            body {font-family: Microsoft YaHei, Helvetica, Arial, sans-serif !important;}
        </style>
    </head>
    <body onload="sortTable(5);sortTable(5);">
        <nav class="navbar navbar-toggleable-md navbar-light bg-faded">
            <button class="navbar-toggler navbar-toggler-right" type="button" data-toggle="collapse" data-target="#navbar" aria-controls="navbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand" href="https://n0099.cf/tbm">贴吧云监控</a>
            <div class="collapse navbar-collapse" id="navbar">
                <ul class="navbar-nav mr-auto mt-2 mt-lg-0">
                    <li class="nav-item"><a class="nav-link" href="https://n0099.cf">返回主站</a></li>
                    <li class="nav-item"><a class="nav-link" href="https://n0099.cf/tc">贴吧云签到</a></li>
                    <li class="nav-item"><a class="nav-link" href="https://n0099.cf/vtop">模拟城市吧吧务后台公开</a></li>
                </ul>
                <a class="navbar-text my-2 my-lg-0" href="https://jq.qq.com/?_wv=1027&k=41RdoBF">四叶重工QQ群：292311751</a>
            </div>
        </nav>
        <br />
        <div class="container">
            <div class="row clearfix">
                <div class="col-md-12 column">
                    <form class="form-inline form-horizontal" action="https://n0099.cf/tbm/" method="get">
                        <fieldset>
                            <legend>搜索选项</legend>
                            <p class="form-text text-muted">均为选填 未填项将被忽略</p>
                            <div class="form-group">
                                <label for="type">贴子类型：</label>
                                <div class="form-check form-check-inline">
                                    <label class="form-check-label">
                                        <input class="form-check-input" type="checkbox" <?php echo in_array('post', $_GET['type']) ? 'checked="checked"' : null; ?> value="post" name="type[]" />主题贴
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <label class="form-check-label">
                                        <input class="form-check-input" type="checkbox" <?php echo in_array('reply', $_GET['type']) ? 'checked="checked"' : null; ?> value="reply" name="type[]" />回复贴
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <label class="form-check-label">
                                        <input class="form-check-input" type="checkbox" <?php echo in_array('lzl', $_GET['type']) ? 'checked="checked"' : null; ?> value="lzl" name="type[]" />楼中楼
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="forum">查询贴吧：</label>
                                <select class="form-control" name="forum">
                                    <option selected="selected" value="">全部</option>
                                    <?php
                                    foreach ($sql -> query("SELECT DISTINCT forum FROM tbmonitor_post") -> fetch_all(MYSQLI_ASSOC) as $tieba) {
                                        echo '<option' . ($tieba['forum'] == $_GET['forum'] ? ' selected="selected"' : null) . ">{$tieba['forum']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="tid">主题贴tid：</label>
                                <div class="input-group-addon">tieba.baidu.com/p/</div>
                                <input class="form-control" type="number" value=<?php echo empty($_GET['tid']) ? '""' : "\"{$_GET['tid']}\""; ?> name="tid" min="1" placeholder="5000000000" />
                            </div>
                            <div class="form-group">
                                <label for="author">发贴人：</label>
                                <div class="input-group-addon"><i class="fa fa-user-circle" aria-hidden="true"></i></div>
                                <input class="form-control" type="text" value=<?php echo empty($_GET['author']) ? '""' : "\"{$_GET['author']}\""; ?> name="author" placeholder="如n0099" />
                            </div>
                            <div class="form-group">
                                <label for="start_date">记录起始时间：</label>
                                <div class="input-group-addon"><i class="fa fa-calendar-minus-o" aria-hidden="true"></i></div>
                                <input class="form-control" type="date" value=<?php echo empty($_GET['start_date']) ? '""' : "\"{$_GET['start_date']}\""; ?> name="start_date" />
                            </div>
                            <div class="form-group">
                                <label for="end_date">记录结束时间：</label>
                                <div class="input-group-addon"><i class="fa fa-calendar-plus-o" aria-hidden="true"></i></div>
                                <input class="form-control" type="date" value=<?php echo empty($_GET['end_date']) ? '""' : "\"{$_GET['end_date']}\""; ?> name="end_date" />
                            </div>
                            <a href="#" onclick="setDatePicker(0);return false;">今天</a>
                            <a href="#" onclick="setDatePicker(-1);return false;">昨天</a>
                            <a href="#" onclick="setDatePicker(-7);return false;">最近一周</a>
                            <a href="#" onclick="setDatePicker(-30);return false;">最近一月</a>
                            <br /><br />
                            <button type="submit" class="btn btn-primary">查询</button>
                            <a class="btn btn-secondary" href="https://n0099.cf/tbm" role="button">重置</a>
                        </fieldset>
                    </form>
                    <br />
                    <div class="alert alert-info" role="alert">
                        <p>
                            <?php
                            foreach($sql_count as $num) {
                                $sql_count_rows += $num[0];
                            }
                            $items += in_array('post', $_GET['type']) ? 10 : 0;
                            $items += in_array('reply', $_GET['type']) ? 10 : 0;
                            $items += in_array('lzl', $_GET['type']) ? 10 : 0;
                            echo '正在显示第' . ($_GET['pn'] * $items) . '到' . ($_GET['pn'] * $items + $items) . '条记录 第' . ($_GET['pn'] + 1) . '页 共' . ($max_page_num + 1) . "页{$sql_count_rows}条记录";
                            ?><br />
                            点击表头排序 查询结果不按时间分页
                        </p>
                    </div>
                    <table class="table table-hover table-striped table-condensed" id="main">
                        <thead>
                            <tr>
                                <th onclick="sortTable(0);">贴吧名</th>
                                <th onclick="sortTable(1);">贴子类型</th>
                                <th onclick="sortTable(2);">内容</th>
                                <th onclick="sortTable(3);">传送门</th>
                                <th onclick="sortTable(4);">发贴人</th>
                                <th onclick="sortTable(5);">发贴时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($sql_results as $type => $sql_result) {
                                foreach ($sql_result -> fetch_all(MYSQLI_ASSOC) as $row) {
                                    $row_type = $type == 'posts' ? '主题贴' : ($type == 'replies' ? '回复贴' : ($type == 'lzl' ? '楼中楼' : null));
                                    $post_portal = $type == 'posts' ? get_post_portal($row['tid']) : ($type == 'replies' ? get_post_portal($row['tid'], $row['pid']) : ($type == 'lzl' ? get_post_portal($row['tid'], $row['pid'], $row['spid']) : null));
                            ?>
                                <tr>
                                    <td><?php echo $row['forum']; ?></td>
                                    <td><?php echo $row_type; ?></td>
                                    <td>
                                        <?php
                                        switch ($type) {
                                            case 'posts':
                                                $row['post_time'] = date('Y-m-d H:i', strtotime($row['post_time']));
                                                $row['latest_reply_time'] = date('Y-m-d H:i', strtotime($row['latest_reply_time']));
                                        ?>
                                                <a data-toggle="collapse" data-target=<?php echo "\"#post_{$row['tid']}\""; ?> href="" aria-expanded="false" aria-controls=<?php echo "\"post_{$row['tid']}\""; ?>>
                                                    <?php echo "{$row['title']}（点击展开/折叠）"; ?>
                                                </a><br />
                                                <div id=<?php echo "\"post_{$row['tid']}\""; ?> class="collapse">
                                                    <p class="card card-block">
                                                        <?php echo $sql -> query("SELECT content FROM tbmonitor_reply WHERE tid = {$row['tid']} AND floor = 1") -> fetch_assoc()['content']; ?>
                                                    </p>
                                                </div>
                                        <?php
                                                echo "主题贴回复数：{$row['reply_num']} 最后回复人：<a href=\"" . get_user_space($row['latest_replyer']) . "\" target=\"_blank\">{$row['latest_replyer']}</a> 最后回复时间：{$row['latest_reply_time']}";
                                                break;
                                            case 'replies':
                                                $row['reply_time'] = date('Y-m-d H:i', strtotime($row['reply_time']));
                                                echo '所回复主题贴：<a href="' . get_post_portal($row['tid']) . '" target="_blank">' . $sql -> query("SELECT title FROM tbmonitor_post WHERE tid = {$row['tid']}") -> fetch_assoc()['title'] . '</a><br />';
                                        ?>
                                                <a data-toggle="collapse" data-target=<?php echo "\"#reply_{$row['pid']}\""; ?> href="" aria-expanded="false" aria-controls=<?php echo "\"reply_{$row['pid']}\""; ?>>
                                                    点击展开/折叠回复
                                                </a><br />
                                                <div id=<?php echo "\"reply_{$row['pid']}\""; ?> class="collapse show">
                                                    <p class="card card-block">
                                                    <?php echo $row['content'] . '<br />'; ?>
                                                    </p>
                                                </div>
                                        <?php
                                                echo "楼层：{$row['floor']} 楼中楼回复数：{$row['lzl_num']}";
                                                break;
                                            case 'lzl':
                                                $row['reply_time'] = date('Y-m-d H:i', strtotime($row['reply_time']));
                                                echo '所回复主题贴：<a href="' . get_post_portal($row['tid']) . '" target="_blank">' . $sql -> query("SELECT title FROM tbmonitor_post WHERE tid = {$row['tid']}") -> fetch_assoc()['title'] . '</a>';
                                                echo ' 所回复楼层：<a href="' . get_post_portal($row['tid'], $row['pid']) . '" target="_blank">' . $sql -> query("SELECT floor FROM tbmonitor_reply WHERE pid = {$row['pid']}") -> fetch_assoc()['floor'] . '楼</a><br />';
                                                echo $row['content'];
                                                break;
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo "<a href=\"{$post_portal}\" target=\"_blank\">传送门</a>"; ?></td>
                                    <td><?php echo '<a href="' . get_user_space($row['author']) . "\" target=\"_blank\">{$row['author']}</a>"; ?></td>
                                    <td><?php echo $type == 'posts' ? $row['post_time'] : $row['reply_time']; ?></td>
                                </tr>
                                <?php
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                    <nav>
                        <ul class="pagination justify-content-end">
                            <?php
                            $pre_class = $_GET['pn'] == 0 ? '"page-item disabled"' : '"page-item"';
                            $pre_href = $_GET['pn'] == 0 ? '""' : '"' . get_url_arguments($_GET['pn'] - 1) . '"';
                            $next_class = $_GET['pn'] == $max_page_num ? '"page-item disabled"' : '"page-item"';
                            $next_href = $_GET['pn'] == $max_page_num ? '""' : '"' . get_url_arguments($_GET['pn'] + 1) . '"';
                            ?>
                            <li class="page-item"><a class="page-link" href=<?php echo '"' . get_url_arguments(0) . '"'; ?>>首页</a></li>
                            <li class=<?php echo $pre_class; ?>><a class="page-link" href=<?php echo $pre_href; ?>>上一页</a></li>
                            <?php
                            $pn = $_GET['pn'] + 1;
                            $start = $pn <= 5 ? 1 : $pn - 5;
                            $end = $max_page_num > 10 && $pn <= 5 ? 10 : ($_GET['pn'] == $max_page_num + 1 ? $pn : ($max_page_num < 10 || $_GET['pn'] >= $max_page_num - 4 ? $max_page_num + 1 : $pn + 5));
                            for ($i = $start; $i <= $end; $i++) {
                                $li_class = $i == $pn ? 'page-item active' : 'page-item';
                                echo "<li class=\"{$li_class}\">".'<a class="page-link" href="' . get_url_arguments($i - 1) . '">' . $i . '</a></li>';
                            }
                            ?>
                            <li class=<?php echo $next_class; ?>><a class="page-link" href=<?php echo $next_href; ?>>下一页</a></li>
                            <li class="page-item"><a class="page-link" href=<?php echo '"' . get_url_arguments($max_page_num) . '"' ; ?>>尾页</a></li>
                        </ul>
                    </nav>
                    <p><?php echo 'PHP耗时' . round(microtime(true) - $time, 10) . '秒，共使用' . round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB内存'; ?></p>
                    <script type="text/javascript">var cnzz_protocol = (("https:" == document.location.protocol) ? " https://" : " http://");document.write(unescape("%3Cspan id='cnzz_stat_icon_1261354059'%3E%3C/span%3E%3Cscript src='" + cnzz_protocol + "s95.cnzz.com/stat.php%3Fid%3D1261354059%26online%3D1%26show%3Dline' type='text/javascript'%3E%3C/script%3E"));</script>
                </div>
            </div>
        </div>
        <script>
        function setDatePicker(days) {
            var start_date = new Date();
            var end_date = new Date();
            start_date.setDate(start_date.getDate() + days);
            start_date.setHours(start_date.getHours() + 8);
            end_date.setHours(end_date.getHours() + 8);
            document.getElementsByName("start_date")[0].value = start_date.toISOString().slice(0, 10);
            document.getElementsByName("end_date")[0].value = end_date.toISOString().slice(0, 10);
        }

        function sortTable(n) {
            var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
            table = document.getElementById("main");
            switching = true;
            //Set the sorting direction to ascending:
            dir = "asc";
            /*Make a loop that will continue until
            no switching has been done:*/
            while (switching) {
                //start by saying: no switching is done:
                switching = false;
                rows = table.getElementsByTagName("TR");
                /*Loop through all table rows (except the
                first, which contains table headers):*/
                for (i = 1; i < (rows.length - 1); i++) {
                    //start by saying there should be no switching:
                    shouldSwitch = false;
                    /*Get the two elements you want to compare,
                    one from current row and one from the next:*/
                    x = rows[i].getElementsByTagName("TD")[n];
                    y = rows[i + 1].getElementsByTagName("TD")[n];
                    /*check if the two rows should switch place,
                    based on the direction, asc or desc:*/
                    if (dir == "asc") {
                        if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                            //if so, mark as a switch and break the loop:
                            shouldSwitch= true;
                            break;
                        }
                    } else if (dir == "desc") {
                        if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
                        //if so, mark as a switch and break the loop:
                        shouldSwitch= true;
                        break;
                        }
                    }
                }
                if (shouldSwitch) {
                    /*If a switch has been marked, make the switch
                    and mark that a switch has been done:*/
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                    //Each time a switch is done, increase this count by 1:
                    switchcount ++;
                } else {
                    /*If no switching has been done AND the direction is "asc",
                    set the direction to "desc" and run the while loop again.*/
                    if (switchcount == 0 && dir == "asc") {
                        dir = "desc";
                        switching = true;
                    }
                }
            }
        }
        </script>
        <script src="https://cdn.bootcss.com/jquery/3.1.1/jquery.min.js"></script>
        <script src="https://cdn.bootcss.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js"></script>
    </body>
</html>