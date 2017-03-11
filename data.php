<?php
use Db\ForumDb;

include 'init.php';
header('Content-Type: application/json');
switch ($_GET['type']) {
    case 'get_forum_data':
        echo getForumData($_GET['forum'], $_GET['num']);
        break;
}

/**
 * @param $post1 Post
 * @param $post2 Post
 * @return int
 */
function sortByTime($post1, $post2) {
    if ($post1->getLastReplyTime()->getTimestamp() == $post2->getLastReplyTime()->getTimestamp())
        return 0;
    return $post1->getLastReplyTime()->getTimestamp() < $post2->getLastReplyTime()->getTimestamp() ? -1 : 1;
}

/**
 * 获得一个吧的数据.
 * @param string $forum
 * @param int $num
 * @return string
 */
function getForumData($forum = "模拟城市", $num = 20) {
    $forumDb = new ForumDb();
    \Db\SyncHelper::fetchSyncable($forumDb);
    $forum = $forumDb->getForum($forum);
    usort($forum->getPosties(), "sortByTime");
    return json_encode(array_slice($forum->getPosties(), 0, $num));
}