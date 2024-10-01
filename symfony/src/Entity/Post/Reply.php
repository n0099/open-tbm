<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity\Post;

use App\Repository\Post\ReplyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReplyRepository::class)]
class Reply extends Post
{
    #[ORM\Column] private int $tid;
    #[ORM\Column, ORM\Id] private int $pid;
    #[ORM\Column] private int $floor;
    #[ORM\Column] private ?int $subReplyCount;
    #[ORM\Column] private ?int $isFold;
    #[ORM\Column] private ?string $geolocation;
    #[ORM\Column] private ?int $signatureId;

    public function getTid(): int
    {
        return $this->tid;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getFloor(): int
    {
        return $this->floor;
    }

    public function getSubReplyCount(): ?int
    {
        return $this->subReplyCount;
    }

    public function getIsFold(): ?int
    {
        return $this->isFold;
    }

    public function getGeolocation(): ?string
    {
        return $this->geolocation;
    }

    public function getSignatureId(): ?int
    {
        return $this->signatureId;
    }
}
