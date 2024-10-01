<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity\Post;

use App\Repository\Post\SubReplyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubReplyRepository::class)]
class SubReply extends Post
{
    #[ORM\Column] private int $pid;
    #[ORM\Column, ORM\Id] private int $spid;
    #[ORM\Column] private int $postedAt;
    #[ORM\Column] private ?int $agreeCount;
    #[ORM\Column] private ?int $disagreeCount;

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getSpid(): int
    {
        return $this->spid;
    }

    public function getPostedAt(): int
    {
        return $this->postedAt;
    }

    public function getAgreeCount(): ?int
    {
        return $this->agreeCount;
    }

    public function getDisagreeCount(): ?int
    {
        return $this->disagreeCount;
    }
}
