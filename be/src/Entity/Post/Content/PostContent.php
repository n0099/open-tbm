<?php

namespace App\Entity\Post\Content;

use App\Entity\BlobResourceGetter;
use Doctrine\ORM\Mapping as ORM;
use TbClient\Wrapper\PostContentWrapper;

#[ORM\MappedSuperclass]
abstract class PostContent
{
    /** @type ?resource */
    #[ORM\Column] private $protoBufBytes;

    public function getContent(): ?array
    {
        return BlobResourceGetter::protoBufWrapper($this->protoBufBytes, PostContentWrapper::class);
    }

    /** @param resource|null $protoBufBytes */
    public function setProtoBufBytes(null $protoBufBytes): void
    {
        $this->protoBufBytes = $protoBufBytes;
    }
}
