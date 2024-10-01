<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity\Post\Content;

use Doctrine\ORM\Mapping as ORM;

class SubReplyContent extends PostContent
{
    #[ORM\Column, ORM\Id] private int $spid;

    public function getSpid(): int
    {
        return $this->spid;
    }
}
