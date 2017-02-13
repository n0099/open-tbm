<?php
ini_set('display_errors', 'On');
$sql = new mysqli('127.0.0.1', 'n0099', 'iloven0099', 'n0099');
?>
<!DOCTYPE html>
<html>
    <head>
        <title>贴吧监控</title>
        <link href="https://cdn.bootcss.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" rel="stylesheet" />
    </head>
    <body>
        <div class="container">
	        <div class="row clearfix">
		        <div class="col-md-12 column">
			        <table class="table">
                        <thead>
                            <tr>
                                <th>贴吧名</th>
                                <th>贴子类型</th>
                                <th>内容</th>
                                <th>传送门</th>
                                <th>时间</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                            $sql_result = $sql -> query("SELECT * FROM tbmonitor_post ORDER BY latest_reply_time DESC LIMIT 50") -> fetch_all(MYSQLI_ASSOC);
                            foreach ($sql_result as $post) { ?>
                            <tr>
                                <th><?php echo $post['forum']; ?></th>
                                <th><?php echo '主题贴'; ?></th>
                                <th><?php ?></th>
                                <th><?php echo "<a href=\"http://tieba.baidu.com/p/{$post['tid']}\">传送门</a>"; ?></th>
                                <th><?php echo $post['latest_reply_time']; ?></th>
                            </tr>
                        <?php }?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script src="https://cdn.bootcss.com/jquery/3.1.1/jquery.min.js"></script>
        <script src="https://cdn.bootcss.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js"></script>
    </body>
</html>