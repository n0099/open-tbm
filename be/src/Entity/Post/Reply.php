<?php

namespace App\Entity\Post;

use App\Entity\BlobResourceGetter;
use App\Repository\Post\ReplyRepository;
use Doctrine\ORM\Mapping as ORM;
use TbClient\Post\Common\Lbs;

#[ORM\Entity(repositoryClass: ReplyRepository::class)]
#[ORM\Table(name: '"tbmc_reply"')]
class Reply extends Post
{
    #[ORM\Column(type: 'bigint')] public int $tid;
    #[ORM\Column(type: 'bigint'), ORM\Id] public int $pid;
    #[ORM\Column] public int $floor;
    #[ORM\Column] public ?int $subReplyCount;
    #[ORM\Column] public ?int $isFold;
    /** @var ?resource */
    #[ORM\Column] public $geolocation;
    #[ORM\Column] public ?int $signatureId;

    public function getPid(): int
    {
        return $this->pid;
    }

    public function setPid(int $value): self
    {
        $this->pid = $value;
        return $this;
    }

    public function getFloor(): int
    {
        return $this->floor;
    }

    public function setFloor(int $value): self
    {
        $this->floor = $value;
        return $this;
    }

    public function getSubReplyCount(): int
    {
        return $this->subReplyCount ?? 0;
    }

    public function setSubReplyCount(?int $value): self
    {
        $this->subReplyCount = $value;
        return $this;
    }

    public function getIsFold(): ?int
    {
        return $this->isFold;
    }

    public function setIsFold(?int $value): self
    {
        $this->isFold = $value;
        return $this;
    }

    public function getGeolocation(): ?array
    {
        return BlobResourceGetter::protoBuf($this->geolocation, Lbs::class);
    }

    /** @param ?resource $geolocation */
    public function setGeolocation($geolocation): self
    {
        $this->geolocation = $geolocation;
        return $this;
    }

    public function getSignatureId(): ?int
    {
        return $this->signatureId;
    }

    public function setSignatureId(?int $value): self
    {
        $this->signatureId = $value;
        return $this;
    }
}
