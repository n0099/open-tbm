<?php
class PostReplyComment implements JsonSerializable
{
    /**
     * 楼中楼ID
     * 等价于百度中的spid
     * @var int
     */
    private $id;

    /**
     * 楼中楼所属回复
     * @var PostReply
     */
    private $reply;

    /**
     * 楼中楼内容
     * @var string
     */
    private $text;

    /**
     * 楼中楼作者
     * @var string
     */
    private $author;

    /**
     * 楼中楼发送时间
     * @var DateTime
     */
    private $replyTime;

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
     * @return PostReply
     */
    public function getReply()
    {
        return $this->reply;
    }

    /**
     * @param PostReply $reply
     */
    public function setReply($reply)
    {
        $this->reply = $reply;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param string $text
     */
    public function setText($text)
    {
        $this->text = $text;
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
    public function getReplyTime()
    {
        return $this->replyTime;
    }

    /**
     * @param DateTime $replyTime
     */
    public function setReplyTime($replyTime)
    {
        $this->replyTime = $replyTime;
    }

    public function jsonSerialize()
    {
        return array(
            'id' => $this->id,
            'author' => $this->author,
            'text' => $this->text,
            'replyTime' => date_timestamp_get($this->replyTime)
        );
    }
}
