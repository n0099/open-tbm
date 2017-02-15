<?php
class Post
{
    /**
     * 贴子所在贴吧
     * @var Forum
     */
    private $forum;

    /**
     * 贴子ID
     * 等价于百度的tid
     * @var int
     */
    private $id;

    /**
     * 贴子的回复，即一个PostReply的数组
     * @var array
     */
    private $replies = array();

    /**
     * 贴子标题
     * @var string
     */
    private $title;

    /**
     * 贴子作者
     * @var string
     */
    private $author;

    /**
     * 发布时间
     * @var DateTime
     */
    private $postTime;

    /**
     * 最新回复者
     * @var string
     */
    private $lastReplier;

    /**
     * 最新回复时间
     * @var DateTime
     */
    private $lastReplyTime;

    /**
     * @return Forum
     */
    public function getForum()
    {
        return $this->forum;
    }

    /**
     * @param Forum $forum
     */
    public function setForum($forum)
    {
        $this->forum = $forum;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return array
     */
    public function getReplies()
    {
        return $this->replies;
    }

    /**
     * 获得总回复数(即总楼层)
     * @return int
     */
    public function getFloors()
    {
        return count($this->replies);
    }

    /**
     * 获得一个回复
     * @param $floor int
     * @return PostReply
     */
    public function getReply($floor)
    {
        return $this->replies[$floor];
    }

    /**
     * @param array $replies
     */
    public function setReplies($replies)
    {
        $this->replies = $replies;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @param string $author
     */
    public function setAuthor($author)
    {
        $this->author = $author;
    }

    /**
     * @return DateTime
     */
    public function getPostTime()
    {
        return $this->postTime;
    }

    /**
     * @param DateTime $postTime
     */
    public function setPostTime($postTime)
    {
        $this->postTime = $postTime;
    }

    /**
     * @return string
     */
    public function getLastReplier()
    {
        return $this->lastReplier;
    }

    /**
     * @param string $lastReplier
     */
    public function setLastReplier($lastReplier)
    {
        $this->lastReplier = $lastReplier;
    }

    /**
     * @return DateTime
     */
    public function getLastReplyTime()
    {
        return $this->lastReplyTime;
    }

    /**
     * @param DateTime $lastReplyTime
     */
    public function setLastReplyTime($lastReplyTime)
    {
        $this->lastReplyTime = $lastReplyTime;
    }
}