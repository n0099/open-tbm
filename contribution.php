<?php
require 'core.php';

$time = microtime(true);
$users = $sql -> query("SELECT author, COUNT(*) FROM tbmonitor_post WHERE post_time BETWEEN \"2016-01-01\" AND \"2016-12-31\" GROUP BY author ORDER BY `COUNT(*)` DESC LIMIT 100") -> fetch_all(MYSQLI_ASSOC);
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
                <div class="col-md-12 column">
                    <table class="table table-hover table-striped table-condensed table-responsive" id="main">
                        <thead>
                            <tr>
                                <th onclick="sortTable(0);">排名</th>
                                <th onclick="sortTable(1);">用户名</th>
                                <th onclick="sortTable(2);">主题贴数</th>
                                <th onclick="sortTable(3);">精品贴数</th>
                                <th onclick="sortTable(4);">获得回复数</th>
                                <th onload="sortTable(5);" onclick="sortTable(5);">贡献分</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($users as $user) {
                                $query = <<<EOT
SELECT `主题贴数`, `精品贴数`, `获得回复数`, (`主题贴数`*20+`精品贴数`*200+`获得回复数`/10)*((`主题贴数`+`精品贴数`)/(`主题贴数`*3)) AS "贡献分"
FROM
    (SELECT COUNT(*) AS "获得回复数" FROM tbmonitor_reply WHERE tid IN (SELECT tid FROM tbmonitor_post WHERE author = "{$user['author']}" AND post_time BETWEEN "2016-01-01" AND "2016-12-31")) AS A
JOIN
    (SELECT COUNT(*) AS "主题贴数" FROM tbmonitor_post WHERE author = "{$user['author']}" AND post_time BETWEEN "2016-01-01" AND "2016-12-31") AS B
JOIN
    (SELECT COUNT(*) AS "精品贴数" FROM tbmonitor_post WHERE author = "{$user['author']}" AND is_good = true AND post_time BETWEEN "2016-01-01" AND "2016-12-31") AS C
EOT;
                                foreach ($sql -> query($query) -> fetch_all(MYSQLI_ASSOC) as $row) {
                            ?>
                                <tr>
                                    <td><!--序号--></td>
                                    <td><?php echo $user['author']; ?></td>
                                    <td><?php echo $row['主题贴数']; ?></td>
                                    <td><?php echo $row['精品贴数']; ?></td>
                                    <td><?php echo $row['获得回复数']; ?></td>
                                    <td><?php echo round($row['贡献分'], 2); ?></td>
                                </tr>
                            <?php
                                    }
                                }
                            ?>
                        </tbody>
                    </table>
                    <div class="text-center">
                        <p><?php echo 'PHP耗时' . round(microtime(true) - $time, 10) . '秒，共使用' . round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB内存'; ?></p>
                        <script type="text/javascript">var cnzz_protocol = (("https:" == document.location.protocol) ? " https://" : " http://");document.write(unescape("%3Cspan id='cnzz_stat_icon_1261354059'%3E%3C/span%3E%3Cscript src='" + cnzz_protocol + "s95.cnzz.com/stat.php%3Fid%3D1261354059%26online%3D1%26show%3Dline' type='text/javascript'%3E%3C/script%3E"));</script>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.bootcss.com/jquery/3.1.1/jquery.min.js"></script>
        <script src="https://cdn.bootcss.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js"></script>
        <script>
        $(function(){
            var len = $('table tr').length;
            for(var i = 1; i<len; i++) {
                $('table tr:eq('+i+') td:first').text(i);
            }
        });

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
    </body>
</html>