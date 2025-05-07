<?php

namespace App\Entity\Post\Content;

use App\Entity\BlobResourceGetter;
use Doctrine\ORM\Mapping as ORM;
use TbClient\Wrapper\PostContentWrapper;

#[ORM\MappedSuperclass]
abstract class PostContent
{
    #[ORM\Column] private int $fid;
    /** @var ?resource */
    #[ORM\Column] private $protoBufBytes;

    public function getFid(): int
    {
        return $this->fid;
    }

    public function setFid(int $fid): self
    {
        $this->fid = $fid;
        return $this;
    }

    public function getContent(): ?array
    {
        return BlobResourceGetter::protoBufWrapper($this->protoBufBytes, PostContentWrapper::class);
    }

    /** @param ?resource $protoBufBytes */
    public function setProtoBufBytes($protoBufBytes): self
    {
        $this->protoBufBytes = $protoBufBytes;
        return $this;
    }
}
