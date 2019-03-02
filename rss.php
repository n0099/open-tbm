<?php
require 'core.php';

$_GET['forum'] = $sql -> escape_string($_GET['forum']);
$_GET['author'] = $sql -> escape_string($_GET['author']);
$_GET['type'] = empty($_GET['type']) ? ['post', 'reply', 'lzl'] : $_GET['type'];

function get_url_arguments($forum = null, $author = null) {
    $arguments = [
        'forum' => null,
        'author' => null,
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
    } else {
        unset($arguments['type']);
    }
    return 'https://n0099.cf/tbm/?' . implode('&', $arguments);
}

$sql_limit = 'LIMIT 30';
$sql_conditions = [
    'forum' => !empty($_GET['forum']) ? "forum = \"{$_GET['forum']}\"" : null,
    'author' => !empty($_GET['author']) ? "author = \"{$_GET['author']}\"" : null,
];
foreach($sql_conditions as $condition => $value) {
    if (empty($value)) { unset($sql_conditions[$condition]); }
}
$sql_condition = empty($sql_conditions) ? null : 'WHERE ' . implode(' AND ', $sql_conditions);
$sql_order_by = 'ORDER BY post_time DESC';

if (in_array('post', $_GET['type'])) {
    $sql_posts = "SELECT * FROM tbmonitor_post {$sql_condition} {$sql_order_by} {$sql_limit}";
}
$sql_order_by = 'ORDER BY reply_time DESC';
if (in_array('reply', $_GET['type'])) {
    $sql_replies = "SELECT * FROM tbmonitor_reply {$sql_condition} {$sql_order_by} {$sql_limit}";
}
if (in_array('lzl', $_GET['type'])) {
    $sql_lzl = "SELECT * FROM tbmonitor_lzl {$sql_condition} {$sql_order_by} {$sql_limit}";
}

$sql_results = [
    'posts' => empty($sql_posts) ? null : $sql -> query($sql_posts),
    'replies' => empty($sql_replies) ? null : $sql -> query($sql_replies),
    'lzl' => empty($sql_lzl) ? null : $sql -> query($sql_lzl)
];
foreach($sql_results as $type => $query) {
    if (empty($query)) { unset($sql_results[$type]); }
}
?>
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>贴吧云监控RSS</title>
        <link><?php echo get_url_arguments($_GET['forum'], $_GET['author']); ?></link>
        <description>test</description>
<?php
foreach ($sql_results as $type => $sql_result) {
    foreach ($sql_result -> fetch_all(MYSQLI_ASSOC) as $row) {
        switch ($type) {
            case 'posts':
?>
                <item>
                    <title><?php echo $row['title']; ?></title>
                    <author><?php echo $row['author']; ?></author>
                    <category><?php echo "{$row['forum']}吧主题贴"; ?></category>
                    <link><?php echo get_post_portal($row['tid']); ?></link>
                    <description><?php echo preg_replace('/<img(.*?)src="(https:\/\/imgsa.baidu.com)(.*?)"(.*?)>/', '<img$1src="http://imgsrc.baidu.com$3"$4', $sql -> query("SELECT content FROM tbmonitor_reply WHERE tid = {$row['tid']} AND floor = 1") -> fetch_assoc()['content']); ?></description>
                    <pubDate><?php echo $row['post_time']; ?></pubDate>
                </item>
        <?php
                break;
            default:
                $row['content'] = preg_replace('/<img(.*?)src="(https:\/\/imgsa.baidu.com)(.*?)"(.*?)>/', '<img$1src="http://imgsrc.baidu.com$3"$4', $row['content']);            
        ?>
                <item>
                    <title><?php echo strip_tags($row['content']); ?></title>
                    <author><?php echo $row['author']; ?></author>
                    <category><?php echo $row['forum'] . ($type == 'replies' ? '吧回复贴' : '吧楼中楼'); ?></category>
                    <link><?php echo $type == 'replies' ? get_post_portal($row['tid'], $row['pid']) : get_post_portal($row['tid'], $row['pid'], $row['spid']); ?></link>
                    <description><?php echo $row['content']; ?></description>
                    <pubDate><?php echo $row['reply_time']; ?></pubDate>
                </item>
        <?php
                break;
        }
    }
}
?>
    </channel>
</rss>