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
                    <div id="cron_chart" style="height: 350px"></div>
                </div>
            </div>
        </div>
        <script>
        var ajax = $.ajax({url:'https://n0099.cf/tbm/ajax.php', data:{'type':'get_cron_time', 'days':'30'}, async:false});
        var ajax_response = eval(ajax.responseText);        
        var cron_times = new Array();
        for(var i = 0, l = ajax_response.length; i < l; i++) {
           cron_times.push(new Array(ajax_response[i]['date'], ajax_response[i]['time']));
        }

        var myChart = echarts.init(document.getElementById('cron_chart'));
        var option = {
            title: {
                text: 'cron耗时',
                subtext: '纯属虚构，至于你信不信，反正认真你就输了'
            },
            tooltip: { trigger: 'axis' },
            legend: { data:['耗时'] },
            toolbox: {
                feature: {
                    dataZoom: { yAxisIndex: false },
                restore: {},
                saveAsImage: {}
                }
            },
            dataZoom: [
                {
                    type: 'slider',
                    start : 90,
                    xAxisIndex: [0],
                },{
                    type: 'inside',
                    start : 90,
                    xAxisIndex: [0],
                }
            ],
            xAxis: {
                type: 'time',
                name: '时间',
            },
            yAxis: {},
            series: [{
                name: '耗时',
                type: 'line',
                step: 'middle',
                sampling: 'average',
                data: cron_times,
                markLine: {
                    data: [{type: 'average', name: '平均值'}]
                },
            }]
        };
        myChart.setOption(option);
        </script>
        <script src="https://cdn.bootcss.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js"></script>
    </body>
</html>