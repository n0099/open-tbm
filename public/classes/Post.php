<?php
class Post implements JsonSerializable
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
     * 贴子类型
     * @var string
     */
    private $type;

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
     * 生成一个发贴时间的字符串.
     * @return string
     */
    public function getPostTimeString()
    {
        return $this->getPostTime()->format("Y-m-d H:i:s");
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

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    public function getText()
    {
        return array_values($this->getReplies())[0]->getText();
    }

    public function jsonSerialize()
    {
        return array(
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'postTime' => date_timestamp_get($this->postTime),
            'lastReplier' => $this->lastReplier,
            'lastReplyTime' => date_timestamp_get($this->lastReplyTime),
            'type' => $this->type,
            'replies' => $this->replies
        );
    }
}