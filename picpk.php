<?php
require 'core.php';

$time = microtime(true);
$_GET['round_tid'] = (int)$_GET['round_tid'];
$_GET['round_floor'] = (int)$_GET['round_floor'];
$picpk_round_pid = $sql -> query("SELECT pid FROM tbmonitor_reply WHERE tid={$_GET['round_tid']} AND floor = {$_GET['round_floor']}") -> fetch_assoc()['pid'];
$thread_reply = $sql -> query("SELECT author, content FROM tbmonitor_reply WHERE tid={$_GET['round_tid']} AND floor >= {$_GET['round_floor']}") -> fetch_all(MYSQLI_ASSOC);
$thread_lzl = $sql -> query("SELECT author, content FROM tbmonitor_lzl WHERE tid={$_GET['round_tid']} AND pid >= {$picpk_round_pid}") -> fetch_all(MYSQLI_ASSOC);
$picpk_groups = [];
$picpk_players_regex = [];

function check_vote($voter, $comment) {
    global $picpk_groups;
    global $picpk_players_regex;
    foreach ($picpk_groups as $group => &$players) {
        foreach ($picpk_players_regex as $player => $player_regex) {
            if (preg_match('/' . implode('|', $player_regex) . '/i', $comment)) {
                if (array_key_exists($player, $players)) {
                    foreach ($players as $players_info) {
                        if (in_array($voter, array_keys($players_info['voters']))) {
                            $is_repeat_vote = true;
                        }
                    }
                    if ($is_repeat_vote == false) {
                        $is_voted = true;
                        $is_repeat_vote = true;
                        $players[$player]['voters'][$voter]['comment'] = $comment;
                    }
                }
            }
            $is_repeat_vote = false;
        }
    }
    return $is_voted;
}

$round_reply = $thread_reply[0]['content'];
preg_match_all('/<img.*?src="(.*?)".*?>/', $round_reply, $picpk_pics);
$picpk_pics = str_replace('https://imgsa.baidu.com', 'http://imgsrc.baidu.com', $picpk_pics[1]);
$round_reply = str_replace('『', "\r\n『", strip_tags($round_reply)) . "\r\n";
preg_match_all('/『(.*?)』(.*?)vs(.*?)\s/', $round_reply, $regex_match, PREG_SET_ORDER);
foreach ($regex_match as $picpk_picgroup) {
    $picpk_groups[$picpk_picgroup[1]][$picpk_picgroup[2]]['voters'] = [];
    $picpk_groups[$picpk_picgroup[1]][$picpk_picgroup[3]]['voters'] = [];
    for ($i = 2; $i <= 3; $i++) {
        preg_match_all('/\d+|[a-zA-Z]+|[\x{4e00}-\x{9fa5}]{2,}/u', $picpk_picgroup[$i], $username_split);
        $picpk_players_regex[$picpk_picgroup[$i]] = $username_split[0];
    }
}
// custom player username regex
$picpk_players_regex['时情丶化忆'] = ['时请', '时情', '化忆'];
$picpk_players_regex['YSL'] = ['YSL', 'YST', '刺客', '无敌'];
$picpk_players_regex['摆渡'][] = '抓到';
foreach ($picpk_pics as $pic) {
    $pic_index = array_search($pic, $picpk_pics);
    $a = array_values($picpk_groups)[$pic_index / 2];
    $b = $pic_index >= 2 ? ($pic_index >= 4 ? 2 : 1) : 0;
    $x = array_keys($picpk_groups)[$b];
    $y = array_keys($a)[(int)(($pic_index % 2 == 0) == false)];
    $picpk_groups[$x][$y]['pic'] = $pic;
}
array_shift($thread_reply);

foreach ($thread_reply as $reply) {
    if (substr($reply['content'], 0, strlen('目前票数')) != '目前票数') {
    if (check_vote($reply['author'], strip_tags($reply['content'])) == true) {
        //echo "{$reply['author']}：{$reply['content']}";
    }
    }
}
foreach ($thread_lzl as $lzl) {
    $lzl['content'] = strip_tags($lzl['content']);
    $lzl['content'] = preg_match('/回复 .*?(:|：)(.*)/', $lzl['content'], $split_reply_user) == 0 ? $lzl['content'] : $split_reply_user[1];
    if (check_vote($lzl['author'], $lzl['content']) == true) {
        //echo "{$lzl['author']}：{$lzl['content']}";
    }
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
    <body>
        <nav class="navbar navbar-toggleable-md navbar-light bg-faded">
            <button class="navbar-toggler navbar-toggler-right" type="button" data-toggle="collapse" data-target="#navbar" aria-controls="navbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand" href="https://n0099.cf/tbm">贴吧云监控</a>
            <div class="collapse navbar-collapse" id="navbar">
                <ul class="navbar-nav mr-auto mt-2 mt-lg-0">
                    <li class="nav-item"><a class="nav-link" href="https://n0099.cf">返回主站</a></li>
                    <li class="nav-item"><a class="nav-link" href="https://n0099.cf/tc">贴吧云签到</a></li>
                    <li class="nav-item"><a class="nav-link" href="https://n0099.cf/vtop">模拟城市吧吧务公开后台</a></li>
                </ul>
                <a class="navbar-text my-2 my-lg-0" href="https://jq.qq.com/?_wv=1027&k=41RdoBF">四叶重工QQ群：292311751</a>
            </div>
        </nav>
        <br />
        <div class="container">
            <div class="row clearfix">
                <?php foreach ($picpk_groups as $group => $players) { ?>
                    <h3><?php echo "{$group}组"; ?></h3>                
                    <div class="row">                    
                        <?php foreach ($players as $player_name => $player_info) { ?>
                            <div class="col-md-6 column">
                                <div class="card">
                                    <img class="card-img-top" src="<?php echo $player_info['pic']; ?>" />
                                    <div class="card-block">
                                        <h5 class="card-title">
                                            <?php echo "作者：{$player_name}"; ?>
                                            <small class="text-muted"><?php echo count($player_info['voters']) . '票'; ?></small>
                                        </h5>
                                        <p class="card-text">
                                            投票者：<br />
                                            <?php foreach ($player_info['voters'] as $voter => $comment) { ?>
                                                <a href="<?php echo get_user_space($voter); ?>" target="_blank" data-toggle="tooltip" data-placement="right" data-html="true" data-original-title="<?php echo $comment['comment']; ?>"><?php echo $voter; ?></a><br />
                                            <?php } ?><br />
                                        </p>
                                        <a class="btn btn-primary" href="<?php echo get_post_portal($_GET['round_tid'], $picpk_round_pid); ?>" target="_blank">投票</a>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
            <div class="text-center">
                <p><?php echo 'PHP耗时' . round(microtime(true) - $time, 10) . '秒，共使用' . round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB内存'; ?></p>
                <script type="text/javascript">var cnzz_protocol = (("https:" == document.location.protocol) ? " https://" : " http://");document.write(unescape("%3Cspan id='cnzz_stat_icon_1261354059'%3E%3C/span%3E%3Cscript src='" + cnzz_protocol + "s95.cnzz.com/stat.php%3Fid%3D1261354059%26online%3D1%26show%3Dline' type='text/javascript'%3E%3C/script%3E"));</script>
            </div>
        </div>
        <script src="https://cdn.bootcss.com/jquery/3.1.1/jquery.min.js"></script>
        <script src="https://cdn.bootcss.com/tether/1.4.0/js/tether.min.js"></script>
        <script src="https://cdn.bootcss.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js"></script>
        <script>
            $(function () {
                $('[data-toggle="tooltip"]').tooltip()
            })
        </script>
    </body>
</html>