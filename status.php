<?php
ini_set('display_errors', 'On');
date_default_timezone_set('PRC');
$sql = new mysqli('127.0.0.1', 'n0099', 'iloven0099', 'n0099');

function get_cron_time($minutes, $get_value) {
    $value = round($GLOBALS['sql'] -> query("SELECT AVG(time) FROM tbmonitor_time WHERE date >= DATE_ADD(NOW(), INTERVAL -{$minutes} MINUTE)") -> fetch_all(MYSQLI_ASSOC)[0]['AVG(time)'], 2);
    if ($get_value == false) { return empty($value) ? '未知' : $value; }
    switch ($value) {
        case $value >= 60:
            return '低';
        case $value >= 30:
            return '中';
        case $value < 30:
            return '高';
        default:
            return '未知';
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
        <script src="https://cdn.bootcss.com/echarts/3.4.0/echarts.min.js"></script>
        <script src="https://cdn.bootcss.com/jquery/3.1.1/jquery.min.js"></script>
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
                <div class="col-md-12 column">
                    <ul class="nav nav-tabs">
                        <li class="nav-item">
                            <a class="nav-link" href="https://n0099.cf/tbm">记录查询</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="https://n0099.cf/tbm/status.php">统计信息</a>
                        </li>
                    </ul>
                    <div class="alert alert-warning" role="alert">
                        <h5>目前监控效率：<strong><?php echo get_cron_time(5, true); ?></strong></h5>
                        <span>最近5/10/15分钟cron耗时：<?php echo get_cron_time(5, false); ?> / <?php echo get_cron_time(10, false); ?> / <?php echo get_cron_time(15, false); ?> 秒</span>
                    </div>
                    <div id="cron_chart" style="height: 350px"></div>
                    <div id="post_count_day_chart" style="height: 350px"></div>
                    <div id="post_count_hour_chart" style="height: 350px"></div>
                    <script type="text/javascript">var cnzz_protocol = (("https:" == document.location.protocol) ? " https://" : " http://");document.write(unescape("%3Cspan id='cnzz_stat_icon_1261354059'%3E%3C/span%3E%3Cscript src='" + cnzz_protocol + "s95.cnzz.com/stat.php%3Fid%3D1261354059%26online%3D1%26show%3Dline' type='text/javascript'%3E%3C/script%3E"));</script>
                </div>
            </div>
        </div>
        <script>
        var chart_option = {
            toolbox: {
                feature: {
                    magicType: {},
                    dataZoom: {yAxisIndex: false},
                    restore: {},
                    saveAsImage: {}
                }
            },
            dataZoom: [
                {
                    type: 'slider',
                    xAxisIndex: 0
                },{
                    type: 'inside',
                    xAxisIndex: 0
                }
            ]
        };
        var ajax_response = eval($.ajax({url:'https://n0099.cf/tbm/ajax.php', data:{'type':'get_cron_time', 'days':'30'}, async:false}).responseText);
        var cron_times = new Array();
        for(var i = 0, l = ajax_response.length; i < l; i++) {
           cron_times.push(new Array(ajax_response[i]['date'], ajax_response[i]['time']));
        }
        chart_option['dataZoom'][0]['start'] = 90;
        chart_option['dataZoom'][1]['start'] = 90;
        chart_option['title'] = {
            text: 'cron耗时',
            subtext: '纯属虚构，至于你信不信，反正认真你就输了'
        };
        chart_option['tooltip'] = {trigger: 'axis'};
        chart_option['legend'] = {data: ['耗时（秒）']};
        chart_option['xAxis'] = {type: 'time', name: '时间'};
        chart_option['yAxis'] = {};
        chart_option['series'] = {
            name: '耗时（秒）',
            type: 'line',
            step: 'middle',
            sampling: 'average',
            data: cron_times,
            markLine: {
                data: [{type: 'average', name: '平均值'}]
            }
        };
        echarts.init(document.getElementById('cron_chart')).setOption(chart_option);

        ajax_response = eval($.ajax({url:'https://n0099.cf/tbm/ajax.php', data:{'type':'get_post_count_by_day', 'days':'30', 'post':'post', 'forum':'模拟城市'}, async:false}).responseText);
        var post_count_day = new Array();
        for(var i = 0, l = ajax_response.length; i < l; i++) {
           post_count_day.push(new Array(ajax_response[i]['DATE'], ajax_response[i]['COUNT(*)']));
        }
        ajax_response = eval($.ajax({url:'https://n0099.cf/tbm/ajax.php', data:{'type':'get_post_count_by_day', 'days':'30', 'post':'reply', 'forum':'模拟城市'}, async:false}).responseText);
        var reply_count_day = new Array();
        for(var i = 0, l = ajax_response.length; i < l; i++) {
           reply_count_day.push(new Array(ajax_response[i]['DATE'], ajax_response[i]['COUNT(*)']));
        }
        ajax_response = eval($.ajax({url:'https://n0099.cf/tbm/ajax.php', data:{'type':'get_post_count_by_day', 'days':'30', 'post':'lzl', 'forum':'模拟城市'}, async:false}).responseText);
        var lzl_count_day = new Array();
        for(var i = 0, l = ajax_response.length; i < l; i++) {
           lzl_count_day.push(new Array(ajax_response[i]['DATE'], ajax_response[i]['COUNT(*)']));
        }

        chart_option['dataZoom'][0]['start'] = 0;
        chart_option['dataZoom'][1]['start'] = 0;
        chart_option['toolbox']['feature']['magicType'] = {type: ['stack', 'tiled']};
        chart_option['title'] = {
            text: '模拟城市吧最近30天贴子数量',
            subtext: '纯属虚构，至于你信不信，反正认真你就输了'
        };
        chart_option['tooltip'] = {trigger: 'axis'};
        chart_option['legend'] = {data: ['主题贴', '回复贴', '楼中楼']};
        chart_option['xAxis'] = {type: 'time', name: '日期'};
        chart_option['yAxis'] = {};
        chart_option['series'] = [
            {
                name: '主题贴',
                type: 'line',
                smooth: true,
                data: post_count_day,
                markLine: {data: [{type: 'average', name: '平均值'}]}
            },{
                name: '回复贴',
                type: 'line',
                smooth: true,
                data: reply_count_day,
                markLine: {data: [{type: 'average', name: '平均值'}]}
            },{
                name: '楼中楼',
                type: 'line',
                smooth: true,
                data: lzl_count_day,
                markLine: {data: [{type: 'average', name: '平均值'}]}
            }
        ];
        echarts.init(document.getElementById('post_count_day_chart')).setOption(chart_option);

        ajax_response = eval($.ajax({url:'https://n0099.cf/tbm/ajax.php', data:{'type':'get_post_count_by_hour', 'days':'1', 'post':'post', 'forum':'模拟城市'}, async:false}).responseText);
        var post_count_hour = new Array();
        for(var i = 0, l = ajax_response.length; i < l; i++) {
           post_count_hour.push(new Array(new Date().toISOString().slice(0, 10) + ' ' + ajax_response[i]['HOUR'] + ':00:00', ajax_response[i]['COUNT(*)']));
        }
        ajax_response = eval($.ajax({url:'https://n0099.cf/tbm/ajax.php', data:{'type':'get_post_count_by_hour', 'days':'1', 'post':'reply', 'forum':'模拟城市'}, async:false}).responseText);
        var reply_count_hour = new Array();
        for(var i = 0, l = ajax_response.length; i < l; i++) {
           reply_count_hour.push(new Array(new Date().toISOString().slice(0, 10) + ' ' + ajax_response[i]['HOUR'] + ':00:00', ajax_response[i]['COUNT(*)']));
        }
        ajax_response = eval($.ajax({url:'https://n0099.cf/tbm/ajax.php', data:{'type':'get_post_count_by_hour', 'days':'1', 'post':'lzl', 'forum':'模拟城市'}, async:false}).responseText);
        var lzl_count_hour = new Array();
        for(var i = 0, l = ajax_response.length; i < l; i++) {
           lzl_count_hour.push(new Array(new Date().toISOString().slice(0, 10) + ' ' + ajax_response[i]['HOUR'] + ':00:00', ajax_response[i]['COUNT(*)']));
        }

        chart_option['dataZoom'][0]['start'] = 0;
        chart_option['dataZoom'][1]['start'] = 0;
        chart_option['title'] = {
            text: '模拟城市吧最近24小时贴子数量',
            subtext: '纯属虚构，至于你信不信，反正认真你就输了'
        };
        chart_option['tooltip'] = {trigger: 'axis'};
        chart_option['legend'] = {data: ['主题贴', '回复贴', '楼中楼']};
        chart_option['xAxis'] = {type: 'time', name: '日期'};
        chart_option['yAxis'] = {};
        chart_option['series'] = [
            {
                name: '主题贴',
                type: 'line',
                smooth: true,
                data: post_count_hour,
                markLine: {data: [{type: 'average', name: '平均值'}]}
            },{
                name: '回复贴',
                type: 'line',
                smooth: true,
                data: reply_count_hour,
                markLine: {data: [{type: 'average', name: '平均值'}]}
            },{
                name: '楼中楼',
                type: 'line',
                smooth: true,
                data: lzl_count_hour,
                markLine: {data: [{type: 'average', name: '平均值'}]}
            }
        ];
        echarts.init(document.getElementById('post_count_hour_chart')).setOption(chart_option);
        </script>
        <script src="https://cdn.bootcss.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js"></script>
    </body>
</html>