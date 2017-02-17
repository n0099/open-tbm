<?php
ini_set('display_errors', 'On');
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
                                <th class="col-md-2">发贴时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql_result = $sql -> query('SELECT * FROM tbmonitor_post ORDER BY latest_reply_time DESC LIMIT 50') -> fetch_all(MYSQLI_ASSOC);
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
                                        <?php echo "主题贴回复数：{$post['reply_num']} 最后回复人：{$post['latest_replyer']} 最后回复时间：{$post['latest_reply_time']}"; ?>
                                    </th>
                                    <th><?php echo "<a href=\"http://tieba.baidu.com/p/{$post['tid']}\">传送门</a>"; ?></th>
                                    <th><?php echo "<a href=\"http://tieba.baidu.com/home/main?un={$post['author']}&ie=utf-8\">{$post['author']}</a>"; ?></th>
                                    <th><?php echo $post['latest_reply_time']; ?></th>
                                </tr>
                            <?php }
                            $sql_result = $sql -> query('SELECT * FROM tbmonitor_reply WHERE floor != 1 ORDER BY reply_time DESC LIMIT 50') -> fetch_all(MYSQLI_ASSOC);
                            foreach ($sql_result as $reply) { ?>
                                <tr>
                                    <th><?php echo $reply['forum']; ?></th>
                                    <th>回复贴</th>
                                    <th><?php
                                        echo "所回复主题贴：<a href=\"http://tieba.baidu.com/p/{$reply['tid']}\">".$sql -> query("SELECT title FROM tbmonitor_post WHERE tid = {$reply['tid']}") -> fetch_assoc()['title'].'</a><br />'; 
                                        echo $reply['content'].'<br />';
                                        echo "楼层：{$reply['floor']} 楼中楼回复数：{$reply['lzl_num']}"
                                    ?></th>
                                    <th><?php echo "<a href=\"http://tieba.baidu.com/p/{$reply['tid']}?pid={$reply['pid']}#{$reply['pid']}\">传送门</a>"; ?></th>
                                    <th><?php echo "<a href=\"http://tieba.baidu.com/home/main?un={$reply['author']}&ie=utf-8\">{$reply['author']}</a>"; ?></th>
                                    <th><?php echo $reply['reply_time']; ?></th>
                                </tr>
                            <?php }
                            $sql_result = $sql -> query('SELECT * FROM tbmonitor_lzl ORDER BY reply_time DESC LIMIT 50') -> fetch_all(MYSQLI_ASSOC);
                            foreach ($sql_result as $lzl) { ?>
                                <tr>
                                    <th><?php echo $lzl['forum']; ?></th>
                                    <th>楼中楼</th>
                                    <th><?php
                                        echo "所回复主题贴：<a href=\"http://tieba.baidu.com/p/{$lzl['tid']}\">".$sql -> query("SELECT title FROM tbmonitor_post WHERE tid = {$lzl['tid']}") -> fetch_assoc()['title'].'</a>';
                                        echo " 所回复楼层：<a href=\"http://tieba.baidu.com/p/{$lzl['tid']}?pid={$lzl['pid']}#{$lzl['pid']}\">".$sql -> query("SELECT floor FROM tbmonitor_reply WHERE pid = {$lzl['pid']}") -> fetch_assoc()['floor'].'楼</a><br />'; 
                                        echo $lzl['content']; 
                                    ?></th>
                                    <th><?php echo "<a href=\"http://tieba.baidu.com/p/{$lzl['tid']}?pid={$lzl['pid']}&cid={$lzl['spid']}#{$lzl['spid']}\">传送门</a>"; ?></th>
                                    <th><?php echo "<a href=\"http://tieba.baidu.com/home/main?un={$lzl['author']}&ie=utf-8\">{$lzl['author']}</a>"; ?></th>
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