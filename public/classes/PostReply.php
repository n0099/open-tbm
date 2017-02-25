<?php
class PostReply implements JsonSerializable
{
    /**
     * 回复ID
     * 等价于百度的pid
     * @var int
     */
    private $id;

    /**
     * 回复所属贴
     * @var Post
     */
    private $post;

    /**
     * 回复内容
     * @var string
     */
    private $text;

    /**
     * 回复人
     * @var string
     */
    private $author;

    /**
     * 回复时间
     * @var DateTime
     */
    private $replyTime;

    /**
     * 回复楼中楼，即一个PostReplyComment数组
     * @var array
     */
    private $comments = array();

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
     * @return Post
     */
    public function getPost()
    {
        return $this->post;
    }

    /**
     * @param Post $post
     */
    public function setPost($post)
    {
        $this->post = $post;
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

    /**
     * @return array
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * @param array $comments
     */
    public function setComments($comments)
    {
        $this->comments = $comments;
    }

    public function jsonSerialize()
    {
        return array(
            'id' => $this->id,
            'text' => $this->text,
            'author' => $this->author,
            'replyTime' => date_timestamp_get($this->replyTime),
            'comments' => $this->comments
        );
    }
}