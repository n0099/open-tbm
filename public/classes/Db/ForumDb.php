<?php
namespace Db;


class ForumDb implements ISyncable
{
    /**
     * 贴吧列表
     * @var array
     */
    private $forums;

    /**
     * 获得需要被加载的表.
     * @return string
     */
    public function getTableName()
    {
        return "tb_forum";
    }

    /**
     * 加载数据.
     * @param $results array
     * @return void
     */
    public function loadData($results)
    {
        foreach ($results as $forumData) {
            $forum = new \Forum();
            $forum->setId($forumData['id']);
            $forum->setName($forumData['name']);
            $posties = array();
            if (is_array(json_decode($forumData)))
            foreach (json_decode($forumData['posties'], true) as $postData) {
                $post = new \Post();
                $post->setId($postData['id']);
                $post->setAuthor($postData['author']);
                $post->setForum($forum);
                $post->setLastReplier($postData['lastReplier']);
                $post->setLastReplyTime(date_create($postData['lastReplyTime']));
                $post->setPostTime(date_create($postData['postTime']));
                $post->setTitle(date_create($postData['title']));
                $replies = array();
                foreach ($postData['replies'] as $replyData) {
                    $reply = new \PostReply();
                    $reply->setId($replyData['id']);
                    $reply->setAuthor($replyData['author']);
                    $reply->setPost($post);
                    $reply->setReplyTime(date_create($replyData['replyTime']));
                    $reply->setText($replyData['text']);
                    $comments = array();
                    foreach ($replyData['comments'] as $commentData) {
                        $comment = new \PostReplyComment();
                        $comment->setId($commentData['id']);
                        $comment->setAuthor($commentData['author']);
                        $comment->setReply($reply);
                        $comment->setReplyTime(date_create($commentData['replyTime']));
                        $comment->setText($commentData['text']);
                        array_push($comments, $comment);
                    }
                    $reply->setComments($comments);
                    array_push($replies, $reply);
                }
                $post->setReplies($replies);
                array_push($posties, $post);
            }
            $forum->setPosties($posties);
        }
    }

    /**
     * 将数据保存.
     * @param \Forum $forum
     * @return array
     */
    public function saveData($forum = null)
    {
        if (is_null($forum))
            return array();
        return array(
            'id' => $forum->getId(),
            'name' => $forum->getName(),
            'posties' => json_encode($forum->getPosties())
        );
    }

    /**
     * @param $name
     * @return \Forum
     */
    public function &getForum($name) {
        return $this->forums[$name];
    }

    /**
     * 获得需要被加载的数据.
     * @return array
     */
    public function getDataToLoad()
    {
        return array('id', 'name', 'posties');
    }
}