<?php
ini_set('display_errors', 'On');
$_GET['tid'] = (int)$_GET['tid'];
$_GET['pn'] = (int)$_GET['pn'];

function get_post_portal($tid, $pid = null, $spid = null) {
    $return = "http://tieba.baidu.com/p/{$tid}";
    $return = $pid != null & $spid == null ? "{$return}?pid={$pid}#{$pid}" : $return;
    $return = $pid != null & $spid != null ? "{$return}?pid={$pid}&cid={$spid}#{$spid}" : $return;
    return $return;
}

function get_user_space($username) {
    return "http://tieba.baidu.com/home/main?un={$username}&ie=utf-8";
}

$time = microtime(true);
$sql = new mysqli('127.0.0.1', 'n0099', 'iloven0099', 'n0099');
?>
<!DOCTYPE html>
<html>
    <head>
        <title>贴吧监控</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link href="https://cdn.bootcss.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" rel="stylesheet" />
    </head>
    <body onload="sortTable(5);sortTable(5);">
        <div class="container">
            <div class="row clearfix">
                <div class="col-md-12 column">
                    <p><?php echo empty($_GET['tid']) ? '显示最近30条主题贴/回复贴/楼中楼记录' : '显示此<a href="' . get_post_portal($_GET['tid']) . '" target="_blank">主题贴</a>所有回复贴楼中楼记录'; ?></p>
                    <table class="table table-hover table-striped table-condensed" id="main">
                        <thead>
                            <tr>
                                <th onclick="sortTable(0)">贴吧名</th>
                                <th onclick="sortTable(1)">贴子类型</th>
                                <th onclick="sortTable(2)">内容</th>
                                <th onclick="sortTable(3)">传送门</th>
                                <th onclick="sortTable(4)">发贴人</th>
                                <th onclick="sortTable(5)">发贴时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $count = $sql -> query('SELECT COUNT(*) FROM tbmonitor_post UNION ALL SELECT COUNT(*) FROM tbmonitor_reply WHERE floor != 1 UNION ALL SELECT COUNT(*) FROM tbmonitor_lzl') -> fetch_all(MYSQLI_NUM);
                            foreach ($count as $key => $value) {
                                $pages += $value[0];
                            }
                            $pages =  $pages / 30;
                            $sql_limit = "LIMIT {$_GET['pn']}, " . ($_GET['pn'] + 10);
                            echo $sql_limit;
                            $sql_posts = empty($_GET['tid']) ? "SELECT * FROM tbmonitor_post ORDER BY post_time DESC {$sql_limit}" : "SELECT * FROM tbmonitor_post WHERE tid = {$_GET['tid']}";
                            $sql_posts_result = $sql -> query($sql_posts) -> fetch_all(MYSQLI_ASSOC);
                            $sql_replies = empty($_GET['tid']) ? "SELECT * FROM tbmonitor_reply WHERE floor != 1 ORDER BY reply_time DESC {$sql_limit}" : "SELECT * FROM tbmonitor_reply WHERE tid = {$_GET['tid']} AND floor != 1";
                            $sql_replies_result = $sql -> query($sql_replies) -> fetch_all(MYSQLI_ASSOC);
                            $sql_lzl = empty($_GET['tid']) ? "SELECT * FROM tbmonitor_lzl ORDER BY reply_time DESC {$sql_limit}" : "SELECT * FROM tbmonitor_lzl WHERE tid = {$_GET['tid']}";
                            $sql_lzl_result = $sql -> query($sql_lzl) -> fetch_all(MYSQLI_ASSOC);
                            foreach ($sql_posts_result as $post) {
                                $post['post_time'] = date('Y-m-d H:i', strtotime($post['post_time']));
                                $post['latest_reply_time'] = date('Y-m-d H:i', strtotime($post['latest_reply_time']));
                            ?>
                                <tr>
                                    <td><?php echo $post['forum']; ?></th>
                                    <td>主题贴</th>
                                    <td>
                                        <a data-toggle="collapse" data-target=<?php echo "\"#post_{$post['tid']}\""; ?> href="">
                                            <?php echo "{$post['title']}（点击展开）"; ?>
                                        </a><br />
                                        <p id=<?php echo "\"post_{$post['tid']}\""; ?> class="collapse out">
                                            <?php echo $sql -> query("SELECT content FROM tbmonitor_reply WHERE tid = {$post['tid']} AND floor = 1") -> fetch_assoc()['content']; ?>
                                        </p>
                                        <?php echo "主题贴回复数：{$post['reply_num']} 最后回复人：<a href=\"" . get_user_space($post['latest_replyer']) . "\" target=\"_blank\">{$post['latest_replyer']}</a> 最后回复时间：{$post['latest_reply_time']}"; ?>
                                    </th>
                                    <td><?php echo '<a href="' . get_post_portal($post['tid']) . '" target="_blank">传送门</a>'; ?></th>
                                    <td><?php echo '<a href="' . get_user_space($post['author']) . "\" target=\"_blank\">{$post['author']}</a>"; ?></th>
                                    <td><?php echo $post['post_time']; ?></th>
                                </tr>
                            <?php }
                            foreach ($sql_replies_result as $reply) {
                                $reply['reply_time'] = date('Y-m-d H:i', strtotime($reply['reply_time']));
                            ?>
                                <tr>
                                    <td><?php echo $reply['forum']; ?></th>
                                    <td>回复贴</th>
                                    <td><?php echo '所回复主题贴：<a href="' . get_post_portal($reply['tid']) . '" target="_blank">' . $sql -> query("SELECT title FROM tbmonitor_post WHERE tid = {$reply['tid']}") -> fetch_assoc()['title'] . '</a><br />'; ?>
                                        <a data-toggle="collapse" data-target=<?php echo "\"#reply_{$reply['pid']}\""; ?> href="">
                                            点击展开回复
                                        </a><br />
                                        <p id=<?php echo "\"reply_{$reply['pid']}\""; ?> class="collapse out">
                                            <?php echo $reply['content'] . '<br />'; ?>
                                        </p>
                                        <?php echo "楼层：{$reply['floor']} 楼中楼回复数：{$reply['lzl_num']}"; ?>
                                    </th>
                                    <td><?php echo '<a href="' . get_post_portal($reply['tid'], $reply['pid']) . '" target="_blank">传送门</a>'; ?></th>
                                    <td><?php echo '<a href="' . get_user_space($reply['author']) . "\" target=\"_blank\">{$reply['author']}</a>"; ?></th>
                                    <td><?php echo $reply['reply_time']; ?></th>
                                </tr>
                            <?php }
                            foreach ($sql_lzl_result as $lzl) {
                                $lzl['reply_time'] = date('Y-m-d H:i', strtotime($lzl['reply_time']));
                            ?>
                                <tr>
                                    <td><?php echo $lzl['forum']; ?></th>
                                    <td>楼中楼</th>
                                    <td><?php
                                        echo '所回复主题贴：<a href="' . get_post_portal($lzl['tid']) . '" target="_blank">' . $sql -> query("SELECT title FROM tbmonitor_post WHERE tid = {$lzl['tid']}") -> fetch_assoc()['title'] . '</a>';
                                        echo ' 所回复楼层：<a href="' . get_post_portal($lzl['tid'], $lzl['pid']) . '" target="_blank">' . $sql -> query("SELECT floor FROM tbmonitor_reply WHERE pid = {$lzl['pid']}") -> fetch_assoc()['floor'] . '楼</a><br />';
                                        echo $lzl['content'];
                                    ?></th>
                                    <td><?php echo '<a href="' . get_post_portal($lzl['tid'], $lzl['pid'], $lzl['spid']) . '" target="_blank">传送门</a>'; ?></th>
                                    <td><?php echo '<a href="' . get_user_space($lzl['author'])  .  "\" target=\"_blank\">{$lzl['author']}</a>"; ?></th>
                                    <td><?php echo $lzl['reply_time']; ?></th>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <nav>
                        <ul class="pagination justify-content-end">
                            <?php
                            $pre_class = $_GET['pn'] == 0 ? '"page-item disabled"' : '"page-item"';
                            $pre_href = $_GET['pn'] == 0 ? '""' : '"https://n0099.cf/tbm/?pn=' . ($_GET['pn'] - 10) . '"';
                            $next_class = $_GET['pn'] == intval($pages) + 1 * 10 ? '"page-item disabled"' : '"page-item"';
                            $next_href = $_GET['pn'] == intval($pages) + 1 * 10 ? '""' : '"https://n0099.cf/tbm/?pn=' . ($_GET['pn'] + 10) . '"';
                            ?>
                            <li class=<?php echo $pre_class; ?>><a class="page-link" href=<?php echo $pre_href; ?>>上一页</a></li>
                            <?php
                            for ($i = 1; $i <= intval($pages) + 1; $i++) {
                                if ($i == 10) { break; }
                                $li_class = $_GET['pn'] == $i * 10 ? 'page-item active' : 'page-item';
                                echo "<li class=\"{$li_class}\">".'<a class="page-link" href="https://n0099.cf/tbm/?pn=' . $i * 10 . '">' . $i . '</a></li>';
                            }
                            ?>
                            <li class=<?php echo $next_class; ?>><a class="page-link" href=<?php echo $next_href; ?>>下一页</a></li>
                        </ul>
                    </nav>
                    <p><?php echo 'PHP耗时' . round(microtime(true)-$time, 10) . '秒，共使用' . round(memory_get_peak_usage()/1024/1024, 2) . 'MB内存'; ?></p>
                </div>
            </div>
        </div>
        <script>
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