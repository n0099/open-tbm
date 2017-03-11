<?php
include 'init.php';
$forum = new Forum();
$post = new Post();
$post->setId(0);
$post->setReplies(array(new PostReply()));
$post->setAuthor("gg");
$post->setForum($forum);
$post->setPostTime(date_create());
$post->setLastReplier("ggg");
$post->setLastReplyTime(date_create());
$post->setTitle("ggggggg");
$post->setType("主题贴");
$post2=$post;
$post2->setId(1);
$forum->setPosties(array($post, $post2));
Constants::getSmarty()->assign("forum", $forum);
Constants::getSmarty()->display("index.tpl");
echo json_encode($forum);