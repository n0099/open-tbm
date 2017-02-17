<?php
ini_set('display_errors', 'On');

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
    <body>
        <div class="container">
            <div class="row clearfix">
                <div class="col-md-12 column">
                    <table class="table table-hover table-striped table-condensed">
                        <thead>
                            <tr>
                                <th class="col-xs-2">贴吧名</th>
                                <th class="col-md-2">贴子类型</th>
                                <th class="col-md-2">内容</th>
                                <th class="col-md-2">传送门</th>
                                <th class="col-md-2">发贴人</th>
                                <th class="col-md-2">发贴/最后回复时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql_result = $sql -> query('SELECT * FROM tbmonitor_post ORDER BY latest_reply_time DESC LIMIT 50') -> fetch_all(MYSQLI_ASSOC);
                            $post['post_time'] = date('Y-m-d H:i', $post['post_time']);
                            $post['latest_reply_time'] = date('Y-m-d H:i', $post['latest_reply_time']);
                            foreach ($sql_result as $post) { ?>
                                <tr>
                                    <th><?php echo $post['forum']; ?></th>
                                    <th>主题贴</th>
                                    <th>
                                        <a data-toggle="collapse" data-target=<?php echo "\"#collapse_{$post['tid']}\""; ?> href="">
                                            <?php echo "{$post['title']}（点击展开）"; ?>
                                        </a><br />
                                        <p id=<?php echo "\"collapse_{$post['tid']}\""; ?> class="collapse out">
                                            <?php echo $sql -> query("SELECT content FROM tbmonitor_reply WHERE tid = {$post['tid']} AND floor = 1") -> fetch_assoc()['content']; ?>
                                        </p>
                                        <?php echo "主题贴回复数：{$post['reply_num']} 最后回复人：<a href=\"".get_user_space($post['latest_replyer'])."\" target=\"_blank\">{$post['latest_replyer']}</a> 发贴时间：{$post['post_time']}"; ?>
                                    </th>
                                    <th><?php echo '<a href="'.get_post_portal($post['tid']).'" target="_blank">传送门</a>'; ?></th>
                                    <th><?php echo '<a href="'.get_user_space($post['author'])."\" target=\"_blank\">{$post['author']}</a>"; ?></th>
                                    <th><?php echo $post['latest_reply_time']; ?></th>
                                </tr>
                            <?php }
                            $sql_result = $sql -> query('SELECT * FROM tbmonitor_reply WHERE floor != 1 ORDER BY reply_time DESC LIMIT 50') -> fetch_all(MYSQLI_ASSOC);
                            $reply['reply_time'] = date('Y-m-d H:i', $reply['reply_time']);
                            foreach ($sql_result as $reply) { ?>
                                <tr>
                                    <th><?php echo $reply['forum']; ?></th>
                                    <th>回复贴</th>
                                    <th><?php
                                        echo '所回复主题贴：<a href="'.get_post_portal($reply['tid']).'" target="_blank">'.$sql -> query("SELECT title FROM tbmonitor_post WHERE tid = {$reply['tid']}") -> fetch_assoc()['title'].'</a><br />'; 
                                        echo $reply['content'].'<br />';
                                        echo "楼层：{$reply['floor']} 楼中楼回复数：{$reply['lzl_num']}"
                                    ?></th>
                                    <th><?php echo '<a href="'.get_post_portal($reply['tid'], $reply['pid']).'" target="_blank">传送门</a>'; ?></th>
                                    <th><?php echo '<a href="'.get_user_space($reply['author'])."\" target=\"_blank\">{$reply['author']}</a>"; ?></th>
                                    <th><?php echo $reply['reply_time']; ?></th>
                                </tr>
                            <?php }
                            $sql_result = $sql -> query('SELECT * FROM tbmonitor_lzl ORDER BY reply_time DESC LIMIT 50') -> fetch_all(MYSQLI_ASSOC);
                            $lzl['reply_time'] = date('Y-m-d H:i', $lzl['reply_time']);
                            foreach ($sql_result as $lzl) { ?>
                                <tr>
                                    <th><?php echo $lzl['forum']; ?></th>
                                    <th>楼中楼</th>
                                    <th><?php
                                        echo '所回复主题贴：<a href="'.get_post_portal($lzl['tid']).'" target="_blank">'.$sql -> query("SELECT title FROM tbmonitor_post WHERE tid = {$lzl['tid']}") -> fetch_assoc()['title'].'</a>';
                                        echo ' 所回复楼层：<a href="'.get_post_portal($lzl['tid'], $lzl['pid']).'" target="_blank">'.$sql -> query("SELECT floor FROM tbmonitor_reply WHERE pid = {$lzl['pid']}") -> fetch_assoc()['floor'].'楼</a><br />'; 
                                        echo $lzl['content']; 
                                    ?></th>
                                    <th><?php echo '<a href="'.get_post_portal($lzl['tid'], $lzl['pid'], $lzl['spid']).'" target="_blank">传送门</a>'; ?></th>
                                    <th><?php echo '<a href="'.get_user_space($lzl['author'])."\" target=\"_blank\">{$lzl['author']}</a>"; ?></th>
                                    <th><?php echo $lzl['reply_time']; ?></th>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <?php
                    echo '<p>PHP耗时'.round(microtime(true)-$time, 10).'秒，共使用'.round(memory_get_peak_usage()/1024/1024, 2).'MB内存</p>';
                    ?>
                </div>
            </div>
        </div>
        <script src="https://cdn.bootcss.com/jquery/3.1.1/jquery.min.js"></script>
        <script src="https://cdn.bootcss.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js"></script>
    </body>
</html>