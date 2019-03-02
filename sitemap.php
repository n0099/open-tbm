<?php
require 'core.php';

function get_changefreq($latest_reply_time) {
    $latest_reply_time = new DateTime($latest_reply_time);
    $now = new DateTime();
    $diff = $latest_reply_time->diff($now);
    switch ($diff->days) {
        case $diff->days == 10:
            return 'always';
        case $diff->days <= 30:
            return 'daily';
        case $diff->days <= 60:
            return 'weekly';
        case $diff->days <= 365:
            return 'monthly';
        case $diff->days > 365:
            return 'yearly';
        default:
            return 'always';
    }
}

$sitemap_content = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

XML;

$posts = $sql -> query("SELECT tid, latest_reply_time FROM tbmonitor_post WHERE tid > 5400000000") -> fetch_all(MYSQLI_ASSOC);

foreach ($posts as $post) {
    $sitemap_content .= "<url>\r\n";
    $sitemap_content .= "<loc>https://n0099.cf/tbm/?tid={$post['tid']}</loc>\r\n";
    $lastmod = date(DATE_ATOM, strtotime($post['latest_reply_time']));
    $sitemap_content .= "<lastmod>{$lastmod}</lastmod>\r\n";
    $changefreq = get_changefreq($post['latest_reply_time']);
    $sitemap_content .= "<changefreq>{$changefreq}</changefreq>\r\n";
    $sitemap_content .= "</url>\r\n";
}

$sitemap_content .= '</urlset>';

echo $sitemap_content;
/*
$sitemap_file = fopen('../sitemap_tbm_3.xml', 'w');
flock($sitemap_file, LOCK_EX);
fwrite($sitemap_file, $sitemap_content);
fclose($sitemap_file);
*/
?>