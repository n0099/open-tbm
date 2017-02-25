<?php
class Forum implements JsonSerializable
{
    /**
     * 贴吧ID
     * @var int
     */
    private $id;

    /**
     * 贴吧名称
     * @var string
     */
    private $name;

    /**
     * 所有贴子，即一个Post的数组
     * @var array
     */
    private $posties = array();

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
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return array
     */
    public function getPosties()
    {
        return $this->posties;
    }

    /**
     * @param array $posties
     */
    public function setPosties($posties)
    {
        $this->posties = $posties;
    }

    public function jsonSerialize()
    {
        return array(
            "id" => $this->id,
            "name" => $this->name,
            "posties" => $this->posties
        );
    }
}