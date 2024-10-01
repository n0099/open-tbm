<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity\Post\Content;

use Doctrine\ORM\Mapping as ORM;

class ReplyContent extends PostContent
{
    #[ORM\Column, ORM\Id] private int $pid;

    public function getPid(): int
    {
        return $this->pid;
    }
}
